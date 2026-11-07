<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class AccessControlService
{
    private const CORE_ROLES = ['manager', 'teacher', 'student'];

    public function roles(): Collection
    {
        return Role::where('guard_name', 'api')->orderBy('id')->get();
    }

    public function permissions(): Collection
    {
        return Permission::where('guard_name', 'api')->orderBy('id')->get();
    }

    public function role(string $id, bool $lock = false): Role
    {
        return $this->record(Role::class, $id, $lock);
    }

    public function permission(string $id, bool $lock = false): Permission
    {
        return $this->record(Permission::class, $id, $lock);
    }

    public function saveRole(array $data, ?string $id = null): Role
    {
        return $this->write(function () use ($data, $id) {
            $role = $id === null ? new Role(['guard_name' => 'api']) : $this->role($id, true);
            if ($role->exists && in_array($role->name, self::CORE_ROLES, true) && $role->name !== $data['name']) {
                throw ValidationException::withMessages(['role' => 'Core application roles cannot be renamed.']);
            }
            $role->name = $data['name'];
            if (! $role->save()) throw new RuntimeException('The role could not be saved.');
            return $role;
        }, true);
    }

    public function savePermission(array $data, ?string $id = null): Permission
    {
        return $this->write(function () use ($data, $id) {
            $permission = $id === null ? new Permission(['guard_name' => 'api']) : $this->permission($id, true);
            $permission->name = $data['name'];
            if (! $permission->save()) throw new RuntimeException('The permission could not be saved.');
            return $permission;
        }, true);
    }

    public function deleteRole(string $id): void
    {
        $this->write(function () use ($id) {
            $role = $this->role($id, true);
            if (in_array($role->name, self::CORE_ROLES, true)) {
                throw ValidationException::withMessages(['role' => 'Core application roles cannot be deleted.']);
            }
            if (! $role->delete()) throw new RuntimeException('The role could not be deleted.');
        });
    }

    public function deletePermission(string $id): void
    {
        $this->write(function () use ($id) {
            if (! $this->permission($id, true)->delete()) throw new RuntimeException('The permission could not be deleted.');
        });
    }

    public function roleWithPermissions(string $id): Role
    {
        return $this->loadPermissions($this->role($id));
    }

    public function changePermissions(string $id, array $values, bool $revoke = false): Role
    {
        return $this->write(function () use ($id, $values, $revoke) {
            $role = $this->role($id, true);
            // Resolve and lock the complete list before any pivot is changed.
            $permissions = $this->resolvePermissions($values);
            if ($revoke) {
                foreach ($permissions as $permission) $role->revokePermissionTo($permission);
            } else {
                $role->syncPermissions($permissions);
            }
            return $this->loadPermissions($role);
        });
    }

    private function loadPermissions(Role $role): Role
    {
        return $role->load(['permissions' => fn ($query) => $query->where('guard_name', 'api')->orderBy('id')]);
    }

    private function resolvePermissions(array $values): Collection
    {
        $ids = array_values(array_filter($values, 'is_int'));
        $names = array_values(array_filter($values, 'is_string'));
        $matches = Permission::where('guard_name', 'api')->where(function ($query) use ($ids, $names) {
            $query->whereIn('id', $ids)->orWhereIn('name', $names);
        })->orderBy('id')->lockForUpdate()->get();
        $byId = $matches->keyBy('id');
        $byName = $matches->keyBy('name');
        $selected = new Collection;
        $errors = [];
        foreach ($values as $index => $value) {
            $permission = is_int($value) ? $byId->get($value) : $byName->get($value);
            if (! $permission) {
                $errors['permissions.'.$index] = 'The selected permission is unavailable for the API guard.';
            } elseif ($selected->has($permission->id)) {
                $errors['permissions.'.$index] = 'The same permission cannot be selected more than once.';
            } else {
                $selected->put($permission->id, $permission);
            }
        }
        if ($errors) throw ValidationException::withMessages($errors);
        return $selected->values();
    }

    private function record(string $model, string $id, bool $lock): Model
    {
        $maximum = (string) PHP_INT_MAX;
        if (! preg_match('/^[1-9][0-9]*$/D', $id) || strlen($id) > strlen($maximum)
            || (strlen($id) === strlen($maximum) && strcmp($id, $maximum) > 0)) {
            throw (new ModelNotFoundException)->setModel($model);
        }
        $query = $model::where('guard_name', 'api');
        if ($lock) $query->lockForUpdate();
        return $query->findOrFail($id);
    }

    private function write(callable $action, bool $savingName = false): mixed
    {
        try {
            return DB::transaction($action, 3);
        } catch (UniqueConstraintViolationException $error) {
            if (! $savingName) throw $error;
            // A concurrent duplicate is still a field error, including archived names.
            throw ValidationException::withMessages(['name' => 'The name has already been taken.']);
        } finally {
            // Spatie also invalidates inside its operations; repeat after commit/rollback.
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            auth()->user()?->unsetRelation('roles')->unsetRelation('permissions');
        }
    }
}

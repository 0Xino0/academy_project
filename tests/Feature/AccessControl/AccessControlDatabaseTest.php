<?php

namespace Tests\Feature\AccessControl;

use App\Models\Permission;
use App\Models\Role;
use App\Services\AccessControlService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\IsolatedSchema;
use Tests\TestCase;

class AccessControlDatabaseTest extends TestCase
{
    use IsolatedSchema;

    public static function drivers(): array
    {
        return ['SQLite' => ['sqlite'], 'PostgreSQL (opt-in)' => ['pgsql']];
    }

    #[DataProvider('drivers')]
    public function test_existing_schema_supports_atomic_grants_unique_errors_and_logical_archive(string $driver): void
    {
        config(['cache.default' => 'array', 'permission.cache.store' => 'array']);
        app(PermissionRegistrar::class)->initializeCache();
        $this->withIsolatedSchema($driver, function () {
            foreach (['roles', 'permissions'] as $table) {
                Schema::create($table, function (Blueprint $table) {
                    $table->id(); $table->string('name'); $table->string('guard_name');
                    $table->timestamps(); $table->softDeletes(); $table->unique(['name', 'guard_name']);
                });
            }
            Schema::create('role_has_permissions', function (Blueprint $table) {
                $table->foreignId('permission_id')->constrained('permissions');
                $table->foreignId('role_id')->constrained('roles');
                $table->primary(['permission_id', 'role_id']);
            });
            $service = app(AccessControlService::class);
            $role = $service->saveRole(['name' => 'database-role']);
            $first = $service->savePermission(['name' => 'database-first']);
            $second = $service->savePermission(['name' => 'database-second']);
            $service->changePermissions((string) $role->id, [$first->id, $second->name]);
            $this->assertSame(2, $role->fresh()->permissions->count());
            try {
                $service->changePermissions((string) $role->id, [$first->id, 'absent-permission'], true);
                $this->fail('A missing permission must not partially revoke a valid permission.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('permissions.1', $error->errors());
            }
            $this->assertSame(2, $role->fresh()->permissions->count());
            $service->changePermissions((string) $role->id, [$first->name], true);
            $this->assertSame([$second->id], $role->fresh()->permissions->pluck('id')->all());
            $service->deletePermission((string) $second->id);
            $this->assertSame(0, $role->fresh()->permissions->count());
            $this->assertSame(1, DB::table('role_has_permissions')->where('role_id', $role->id)->count());
            try {
                $service->savePermission(['name' => $second->name]);
                $this->fail('An archived name stays reserved by the database.');
            } catch (ValidationException $error) {
                $this->assertArrayHasKey('name', $error->errors());
            }
            $this->assertSame(2, Permission::withTrashed()->count());
            $this->assertSame(1, Permission::count());
            $service->deleteRole((string) $role->id);
            $this->assertSame(0, Role::count());
            $this->assertSame(1, Role::withTrashed()->count());
            $this->assertSame(1, DB::table('role_has_permissions')->count());
        });
    }
}

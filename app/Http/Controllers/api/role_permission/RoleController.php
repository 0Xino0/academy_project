<?php

namespace App\Http\Controllers\api\role_permission;

use App\Http\Controllers\Concerns\HandlesAccessResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\RolePermissionsRequest;
use App\Http\Requests\RoleRequest;
use App\Services\AccessControlService;

class RoleController extends Controller
{
    use HandlesAccessResponses;

    public function __construct(private AccessControlService $access) {}

    public function index()
    {
        return $this->accessResponse(fn () => response()->json([
            'status' => true, 'message' => 'Roles retrieved successfully', 'data' => $this->access->roles(),
        ]));
    }

    public function store(RoleRequest $request)
    {
        return $this->accessResponse(function () use ($request) {
            $role = $this->access->saveRole($request->validated());
            return response()->json([
                'status' => true, 'message' => 'Role created successfully', 'data' => $role, 'role' => $role,
            ]);
        });
    }

    public function show(string $id)
    {
        return $this->accessResponse(fn () => response()->json([
            'status' => true, 'message' => 'Role retrieved successfully', 'data' => $this->access->role($id),
        ]));
    }

    public function update(RoleRequest $request, string $id)
    {
        return $this->accessResponse(fn () => response()->json([
            'status' => true, 'message' => 'Role updated successfully',
            'data' => $this->access->saveRole($request->validated(), $id),
        ]));
    }

    public function destroy(string $id)
    {
        return $this->accessResponse(function () use ($id) {
            $this->access->deleteRole($id);
            return response()->json(['status' => true, 'message' => 'Role deleted successfully']);
        });
    }

    public function getPermissionsOfRole(string $id)
    {
        return $this->accessResponse(function () use ($id) {
            $role = $this->access->roleWithPermissions($id);
            return response()->json([
                'status' => true, 'message' => 'Role permissions retrieved successfully', 'role' => $role,
                'permissionsOfRole' => $role->permissions,
                'permissonsOfRole' => $role->permissions, // Compatibility with the original response key.
                'permissions' => $this->access->permissions(),
            ]);
        });
    }

    public function givePermissionsToRole(RolePermissionsRequest $request, string $id)
    {
        return $this->changePermissions($request, $id, false);
    }

    public function revokePermissionFromRole(RolePermissionsRequest $request, string $id)
    {
        return $this->changePermissions($request, $id, true);
    }

    private function changePermissions(RolePermissionsRequest $request, string $id, bool $revoke)
    {
        return $this->accessResponse(function () use ($request, $id, $revoke) {
            $role = $this->access->changePermissions($id, $request->validated('permissions'), $revoke);
            return response()->json([
                'status' => true,
                'message' => $revoke ? 'Permissions removed from role successfully' : 'Role permissions replaced successfully',
                'role' => $role, 'permissions' => $role->permissions,
            ]);
        });
    }
}

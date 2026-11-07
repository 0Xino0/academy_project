<?php

namespace App\Http\Controllers\api\role_permission;

use App\Http\Controllers\Concerns\HandlesAccessResponses;
use App\Http\Controllers\Controller;
use App\Http\Requests\PermissionRequest;
use App\Services\AccessControlService;

class PermissionController extends Controller
{
    use HandlesAccessResponses;

    public function __construct(private AccessControlService $access) {}

    public function index()
    {
        return $this->accessResponse(fn () => response()->json([
            'status' => true, 'message' => 'Permissions retrieved successfully', 'data' => $this->access->permissions(),
        ]));
    }

    public function store(PermissionRequest $request)
    {
        return $this->accessResponse(fn () => response()->json([
            'status' => true, 'message' => 'Permission created successfully',
            'data' => $this->access->savePermission($request->validated()),
        ]));
    }

    public function show(string $id)
    {
        return $this->accessResponse(fn () => response()->json([
            'status' => true, 'message' => 'Permission retrieved successfully', 'data' => $this->access->permission($id),
        ]));
    }

    public function update(PermissionRequest $request, string $id)
    {
        return $this->accessResponse(fn () => response()->json([
            'status' => true, 'message' => 'Permission updated successfully',
            'data' => $this->access->savePermission($request->validated(), $id),
        ]));
    }

    public function destroy(string $id)
    {
        return $this->accessResponse(function () use ($id) {
            $this->access->deletePermission($id);
            return response()->json(['status' => true, 'message' => 'Permission deleted successfully']);
        });
    }
}

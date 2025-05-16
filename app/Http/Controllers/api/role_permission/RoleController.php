<?php

namespace App\Http\Controllers\api\role_permission;

use Exception;
use Illuminate\Http\Request;
use App\Http\Requests\RoleRequest;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $role = Role::get();
        return response()->json([
            'data' => $role
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RoleRequest $request)
    {
        $role = Role::create($request->validated());

        if($role)
        {
            return response()->json([
                'message' => 'Role created successfully',
                'role' => $role
            ]);
        }else{
            return response()->json([
                'message' => 'Role could not be created'
            ]);
        }

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try
        {
            $role = Role::findOrFail($id);
        }catch(Exception $e){
            $errormsg = 'that role could not be found \n' . $e->getMessage();
            return response()->json([
               'msg' => $errormsg,
            ]);
        };

        return response()->json([
            'data' => $role
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try
        {
            $role = Role::findOrFail($id);
        }catch(Exception $e){
            $errormsg = 'that role could not be found \n' . $e->getMessage();
            return response()->json([
               'msg' => $errormsg,
            ]);
        };

        $request->validate([
            'name' => 'required|string|unique:roles,name,'.$role->id
        ]);

        $role->update([
            'name' => $request->name,
        ]);

        if($role)
        {
            return response()->json([
                'msg' => 'the role has been updated',
                'data' => $role

            ]);
        }else{
            return response()->json([
                'msg' => 'the role could not be updated'
            ]);
        }
        
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try{
            $role = Role::findOrFail($id);
        }catch(\Exception $e)
        {
            $errormsg = 'Could not find role \n'. $e->getMessage();
            return response()->json([
                'msg' => $errormsg,
            ]);
        }

        $result = $role->delete();

        if($result)
        {
            return response()->json([
               'message' => 'role deleted successfully'
            ]);
        }
        else
        {
            return response()->json([
               'message' => 'Failed to delete role'
            ]);
        }
    }

    // show permissions to assign that to a role
    public function getPermissionsOfRole(string $id)
    {
        try{
            $role = Role::findOrFail($id);
        }catch(\Exception $e)
        {
            $errormsg = 'Could not find role \n'. $e->getMessage();
            return response()->json([
                'msg' => $errormsg,
            ]);
        }

        $permissions = Permission::get();

        return response()->json([
            'role' => $role,
            'permissonsOfRole' => $role->permissions,
            'permissions' => $permissions
        ]);
    

    }

    // give permissions to roles
    public function givePermissionsToRole(Request $request , string $id)
    {
        $request->validate([
            'permissions' => 'required',
        ]);

        try{
            $role = Role::findOrFail($id);
        }catch(\Exception $e){
            $errormsg = 'Could not find role \n'. $e->getMessage();
            return response()->json([
                'msg' => $errormsg,
            ]);
        }

        $role->syncPermissions($request->permissions);

        return response()->json([
           'message' => 'Permissions given to role successfully'
        ]);

        
    }

    // revoke permissions from a role
    public function revokePermissionFromRole(Request $request, string $id)
    {
        $request->validate([
            'permissions' => 'required',
        ]);

        try{
            $role = Role::findOrFail($id);
        }catch(\Exception $e){
            $errormsg = 'Could not find role \n'. $e->getMessage();
            return response()->json([
                'msg' => $errormsg,
            ]);
        }

        foreach ($request->permissions as $permission) {
            $role->revokePermissionTo($permission);        
        }

        return response()->json([
           'message' => 'Permission removed from role successfully',
           'permissions' => $role->permissions
        ]);
    }
}

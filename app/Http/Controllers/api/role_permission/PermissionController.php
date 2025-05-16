<?php

namespace App\Http\Controllers\api\role_permission;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\PermissionRequest;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $permissions = Permission::all();
        return response()->json([
            'data' => $permissions
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
    public function store(PermissionRequest $request)
    {
        
        $permission = Permission::create($request->validated());
        if($permission)
        {
            return response()->json([
                'message' => 'Permission created successfully',
                'data' => $permission
            ]);
        }else{
            return response()->json([
               'message' => 'Failed to create permission'
            ]);
        }

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try{
            $permission = Permission::findOrFail($id);
        }catch(\Exception $e)
        {
            $errormsg = 'Could not find permission \n'. $e->getMessage();
            return response()->json([
                'msg' => $errormsg,
            ]);
        }
        
        return response()->json([
            'data' => $permission
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
    public function update(Request $request,string $id)
    {
        try{
            $permission = Permission::findOrFail($id);
        }catch(\Exception $e)
        {
            $errormsg = 'Could not find permission \n'. $e->getMessage();
            return response()->json([
                'msg' => $errormsg,
            ]);
        }

        $request->validate([
            'name' => 'required|string|unique:permissions,name,'.$permission->id,
        ]);
        
        $permission->update([
            'name' => $request->name,
        ]);

        if($permission)
        {
            return response()->json([
                'message' => 'Permission updated successfully',
                'data' => $permission
            ]);
        }
        else
        {
            return response()->json([
               'message' => 'Failed to update permission'
            ]);
        }
        
        
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try{
            $permission = Permission::findOrFail($id);
        }catch(\Exception $e)
        {
            $errormsg = 'Could not find permission \n'. $e->getMessage();
            return response()->json([
                'msg' => $errormsg,
            ]);
        }

        $result = $permission->delete();

        if($result)
        {
            return response()->json([
               'message' => 'Permission deleted successfully'
            ]);
        }
        else
        {
            return response()->json([
               'message' => 'Failed to delete permission'
            ]);
        }
    }
}

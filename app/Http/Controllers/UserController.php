<?php

namespace App\Http\Controllers;


use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function Index()
    {
        $users = User::get();
        return response()->json([
            'data' => $users
        ]);
    }

    public function edit(string $id)
    {
        try{
            $user = User::findOrFail($id);
        }catch(Exception $e){
            return response()->json([
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
        $roles = Role::pluck('name','name')->all();
        return response()->json([
            'user' => $user,
            'roleOfUsers' => $user->getRoleNames(),
            'roles' => $roles,
        ]);
    }

    public function update(Request $request, string $id)
    {

        try{
            $user = User::findOrFail($id);
        }catch(Exception $e){
            return response()->json([
               'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }
        
        $request->validate([
            'national_id' => 'required|integer|digits_between:10,10|unique:users,national_id,'.$request->id,
            'first_name' =>'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => 'required|integer',
            'roles' => 'required',  
            'email' =>'nullable|email|unique:users,email,'.$request->id.'|email:filter',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $data = [
            'national_id' => $request->national_id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'phone' => $request->phone,
            'email' => $request->email,
        ];

        if(!empty($request->password))
        {
            $data['password'] = Hash::make($request->password);
        }

        // return response()->json([
        //     'message' => 'User updated successfully',
        //     'data' => $data,
        // ]);
        $user->update($data);

        $user->syncRoles($request->roles);

        return response()->json([
            'message' => 'User updated successfully',
            'data' => $user,
            'roleOfUsers' => $user->getRoleNames(),
        ]);

    }

    public function destroy(string $id)
    {
        try{
            $user = User::findOrFail($id);
        }catch(Exception $e){
            return response()->json([
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }

        $result = $user->delete();

        if($result)
        {
            return response()->json([
                'message' => 'User deleted successfully'
            ]);
        }
        else
        {
            return response()->json([
                'message' => 'User could not be deleted'
            ]);
        }
    }
}



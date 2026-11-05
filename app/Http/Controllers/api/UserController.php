<?php

namespace App\Http\Controllers\api;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrationRequest;
use App\Models\Teacher;

class UserController extends Controller
{
    public function Index(User $user)
    {
        $users = User::with('roles')->get();
        return response()->json([
            'data' => $users
        ]);
    }

    public function create()
    {
    }

    public function store(RegistrationRequest $request)
    {
        $user = User::create($request->validated());

        $user->assignRole('teacher');

        if($user)
        {
            Teacher::create(['user_id' => $user->id]);

            return response()->json([
                'status' => true,
                'message' => 'User created successfully',
                'data' => $user,
                'role' => $user->getRoleNames(),
                'teacher' => $user->teacher,
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while creating the user'

            ]);
        }
    }

    public function edit(string $id)
    {
    }

    public function show(string $id)
    {
        $user = User::findOrFail($id);
        $this->authorize('view', $user);

        return response()->json([
            'status' => true,
            'user' => $user,
            'roleOfUsers' => $user->getRoleNames(),
        ]);
    }

    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);
        $this->authorize('update', $user);

        $user->update($request->validate([
            'national_id' => 'required|integer|digits:10|unique:users,national_id,'.$user->id,
            'first_name' =>'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => ['required','regex:/^(\+98)[0-9]{10}$/'], 
            'email' =>'required|email|email:filter|unique:users,email,'.$user->id,
        ]));

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
                'status' => true,
                'message' => 'User deleted successfully'
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => 'User could not be deleted'
            ]);
        }
    }
}



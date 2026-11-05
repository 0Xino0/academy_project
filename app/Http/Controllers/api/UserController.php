<?php

namespace App\Http\Controllers\api;

use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegistrationRequest;
use App\Services\AccountService;

class UserController extends Controller
{
    public function __construct(private AccountService $accounts) {}

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
        $user = $this->accounts->create($request->validated(), 'teacher');

        return response()->json([
            'status' => true,
            'message' => 'User created successfully',
            'data' => $user,
            'role' => $user->getRoleNames(),
            'teacher' => $user->teacher,
        ]);
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
            'national_id' => 'required|digits:10|unique:users,national_id,'.$user->id,
            'first_name' =>'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'phone' => ['required', 'regex:/^(\+98)(9)[0-9]{9}$/', 'unique:users,phone,'.$user->id],
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

        $result = $this->accounts->deleteUser($user);

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



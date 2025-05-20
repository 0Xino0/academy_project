<?php

namespace App\Http\Controllers\api;


use Exception;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\RegistrationRequest;
use App\Models\Teacher;

class UserController extends Controller
{
    public function Index(User $user)
    {
        // $this->authorize('view' , User::class);
        // if(Gate::denies('viewAny' , User::class))
        // {
        //     return response()->json([
        //         'message' => 'You do not have permission to view users'
        //     ], 403);
        // }

        $users = User::with('roles')->get();
        return response()->json([
            'data' => $users
        ]);
    }

    public function create()
    {
        //
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
        // $this->authorize('update' , User::class);
        // if(Gate::denies('view' , User::class))
        // {
        //     return response()->json([
        //         'message' => 'You do not have permission to view this user'
        //     ], 403);
        // }
        
    }

    public function show(string $id)
    {
        // if(Gate::denies('view' , User::class))
        // {
        //     return response()->json([
        //         'message' => 'You do not have permission to view this user'
        //     ], 403);
        // }
        

        try{
            $user = User::findOrFail($id);
        }catch(Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }

        return response()->json([
            'status' => true,
            'user' => $user,
            'roleOfUsers' => $user->getRoleNames(),
        ]);
    }

    public function update(Request $request, string $id)
    {
        // $this->authorize('update' , User::class);
        // if(Gate::denies('update' , User::class))
        // {
        //     return response()->json([
        //         'message' => 'You do not have permission to update this user'
        //     ], 403);
        // }

        
        try{
            $user = User::findOrFail($id);
        }catch(Exception $e){
            return response()->json([
                'status' => false,
               'message' => 'Error: '.$e->getMessage(),
            ], 500);
        }

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
        // $this->authorize('delete' , User::class);
        // if(Gate::denies('delete' , User::class))
        // {
        //     return response()->json([
        //         'message' => 'You do not have permission to delete this user'
        //     ], 403);
        // }
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



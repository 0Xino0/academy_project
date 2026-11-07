<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesPeopleResponses;
use App\Http\Requests\RegistrationRequest;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    use HandlesPeopleResponses;

    public function __construct(private AccountService $accounts) {}

    public function index()
    {
        return $this->peopleResponse(fn () => response()->json([
            'status' => true, 'message' => 'Users retrieved successfully', 'data' => User::with('roles')->orderBy('id')->get(),
        ]));
    }

    public function store(RegistrationRequest $request)
    {
        return $this->peopleResponse(function () use ($request) {
            $user = $this->accounts->create($request->validated(), 'teacher');
            return response()->json([
                'status' => true, 'message' => 'User created successfully', 'data' => $user,
                'role' => $user->getRoleNames(), 'teacher' => $user->teacher,
            ]);
        });
    }

    public function show(string $id)
    {
        return $this->peopleResponse(function () use ($id) {
            $user = User::findOrFail($id);
            $this->authorize('view', $user);
            return response()->json(['status' => true, 'user' => $user, 'roleOfUsers' => $user->getRoleNames()]);
        });
    }

    public function update(Request $request, string $id)
    {
        return $this->peopleResponse(function () use ($request, $id) {
            $user = User::findOrFail($id);
            $this->authorize('update', $user);
            $data = $request->validate([
                'national_id' => 'required|digits:10|unique:users,national_id,'.$user->id,
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'phone' => ['required', 'regex:/^(\+98)(9)[0-9]{9}$/', 'unique:users,phone,'.$user->id],
                'email' => 'required|email|email:filter|unique:users,email,'.$user->id,
            ]);
            $user = $this->accounts->updateUser($user, $data);
            return response()->json([
                'status' => true, 'message' => 'User updated successfully', 'data' => $user, 'roleOfUsers' => $user->getRoleNames(),
            ]);
        });
    }

    public function destroy(string $id)
    {
        return $this->peopleResponse(function () use ($id) {
            $this->accounts->deleteUser(User::findOrFail($id));
            return response()->json(['status' => true, 'message' => 'User deleted successfully']);
        });
    }
}

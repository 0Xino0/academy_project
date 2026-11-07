<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesPeopleResponses;
use App\Models\Teacher;
use App\Services\AccountService;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    use HandlesPeopleResponses;

    public function __construct(private AccountService $accounts) {}

    public function index()
    {
        return $this->peopleResponse(fn () => response()->json([
            'status' => true, 'message' => 'Teachers retrieved successfully',
            'data' => Teacher::with('user')->orderBy('id')->get(),
        ]));
    }

    public function show(string $id)
    {
        return $this->peopleResponse(function () use ($id) {
            $teacher = Teacher::with('user')->findOrFail($id);
            $this->authorize('view', $teacher);
            return response()->json(['status' => true, 'message' => 'Teacher retrieved successfully', 'data' => $teacher]);
        });
    }

    public function updateAdminInfo(Request $request, string $id)
    {
        return $this->peopleResponse(function () use ($request, $id) {
            $teacher = Teacher::findOrFail($id);
            $this->authorize('update', $teacher);
            $data = $request->validate(['salary' => 'required|integer|min:0']);
            return response()->json([
                'status' => true, 'message' => 'Teacher salary updated successfully',
                'data' => $this->accounts->updateTeacherProfile($teacher, $data),
            ]);
        });
    }

    public function update(Request $request, string $id)
    {
        return $this->peopleResponse(function () use ($request, $id) {
            $teacher = Teacher::findOrFail($id);
            $this->authorize('update', $teacher);
            $data = $request->validate([
                'resume' => 'sometimes|nullable|string|max:255',
                'bio' => 'sometimes|nullable|string|max:255',
                'degree' => 'sometimes|nullable|string|max:255',
            ]);
            return response()->json([
                'status' => true, 'message' => 'Teacher information updated successfully',
                'data' => $this->accounts->updateTeacherProfile($teacher, $data),
            ]);
        });
    }

    public function destroy(string $id)
    {
        return $this->peopleResponse(function () use ($id) {
            $this->accounts->deleteTeacherProfile(Teacher::findOrFail($id));
            return response()->json(['status' => true, 'message' => 'Teacher profile deactivated; records retained.', 'error' => null]);
        });
    }
}

<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesPeopleResponses;
use App\Http\Requests\StudentRequest;
use App\Models\ClassModel;
use App\Models\Student;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    use HandlesPeopleResponses;

    public function __construct(private AccountService $accounts) {}

    public function index()
    {
        return $this->peopleResponse(fn () => response()->json([
            'status' => true, 'message' => 'Students retrieved successfully',
            'data' => Student::with('user')->orderBy('id')->get(),
        ]));
    }

    public function indexPerClasses(string $class_id)
    {
        return $this->peopleResponse(function () use ($class_id) {
            $this->authorize('viewStudents', ClassModel::findOrFail($class_id));
            return response()->json([
                'status' => true, 'message' => 'Students retrieved successfully',
                'data' => Student::with('user')->whereHas('registrations', fn ($query) => $query->where('class_id', $class_id))
                    ->orderBy('id')->get(),
            ]);
        });
    }

    public function store(StudentRequest $request)
    {
        return $this->peopleResponse(function () use ($request) {
            $owner = User::findOrFail($request->user_id);
            $this->authorize('create', [Student::class, $owner]);
            return response()->json([
                'status' => true, 'message' => 'Student created successfully',
                'data' => $this->accounts->createStudentProfile($owner, $request->validated()),
            ]);
        });
    }

    public function show(string $id)
    {
        return $this->peopleResponse(function () use ($id) {
            $student = Student::with('user')->findOrFail($id);
            $this->authorize('view', $student);
            return response()->json(['status' => true, 'message' => 'Student retrieved successfully', 'data' => $student]);
        });
    }

    public function showPerClass(string $class_id, string $student_id)
    {
        return $this->peopleResponse(function () use ($class_id, $student_id) {
            $this->authorize('viewStudents', ClassModel::findOrFail($class_id));
            $membership = fn ($query) => $query->where('class_id', $class_id);
            $student = Student::with(['user', 'registrations' => $membership])->whereHas('registrations', $membership)->findOrFail($student_id);
            return response()->json(['status' => true, 'message' => 'Student retrieved successfully', 'data' => $student]);
        });
    }

    public function update(Request $request, string $id)
    {
        return $this->peopleResponse(function () use ($request, $id) {
            $student = Student::findOrFail($id);
            $this->authorize('update', $student);
            $data = $request->validate([
                'father_name' => 'required|string|max:255',
                'father_phone' => ['required', 'regex:/^(\+98)(9)[0-9]{9}$/'],
                'mother_name' => 'required|string|max:255',
                'mother_phone' => ['required', 'regex:/^(\+98)(9)[0-9]{9}$/'],
            ]);
            return response()->json([
                'status' => true, 'message' => 'Student updated successfully',
                'data' => $this->accounts->updateStudentProfile($student, $data),
            ]);
        });
    }

    public function destroy(string $id)
    {
        return $this->peopleResponse(function () use ($id) {
            $this->accounts->deleteStudentProfile(Student::findOrFail($id));
            return response()->json(['status' => true, 'message' => 'Student profile deactivated; records retained.', 'error' => null]);
        });
    }
}

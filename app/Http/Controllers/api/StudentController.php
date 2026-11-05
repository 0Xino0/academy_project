<?php

namespace App\Http\Controllers\api;

use App\Models\User;
use App\Models\Student;
use App\Models\ClassModel;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\StudentRequest;
use App\Services\AccountService;

class StudentController extends Controller
{
    public function __construct(private AccountService $accounts) {}

    /**
     * Display a listing of the resource.
     */

    public function index(Student $student)
    {
        $students = $student::with('user')->get();

        if($students->isEmpty())
        {
            return response()->json([
                'status' => false,
                'message' => 'no students found',
                'data' => null
            ]);
        }else{
            return response()->json([
                'status' => true,
                'message' => 'student retrieved successfully',
                'data' => $students
            ]);
        }
    }
    
    public function indexPerClasses(Student $student,string $class_id)
    {
        $this->authorize('viewStudents', ClassModel::findOrFail($class_id));

        $students = $student::with(['user'])
                            ->whereHas('registrations',function($query) use ($class_id){
                                $query->where('class_id',$class_id);
                            })
                            ->get();

        if($students->isEmpty())
        {
            return response()->json([
                'status' => false,
                'message' => 'no students found',
                'data' => null
            ]);
        }else{
            return response()->json([
                'status' => true,
                'message' => 'student retrieved successfully',
                'data' => $students
            ]);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(string $id)
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StudentRequest $request )
    {
        $owner = User::findOrFail($request->user_id);
        $this->authorize('create', [Student::class, $owner]);
        $student = $this->accounts->createStudentProfile($owner, $request->validated());

        return response()->json([
            'status' => true,
            'message' => 'student created successfully',
            'data' => $student,
        ]);
    }

    /**
     * Display the specified resource.
     */

    public function show(Student $student , string $id)
    {
        $student = Student::with('user')->findOrFail($id);
        $this->authorize('view', $student);

        return response()->json([
            'status' => true,
            'message' => 'student retrieved successfully',
            'data' => $student,
        ]);
    }

    public function showPerClass(Student $student , string $class_id, string $student_id)
    {
        $this->authorize('viewStudents', ClassModel::findOrFail($class_id));
        $student = Student::with(['user', 'registrations' => function ($query) use ($class_id) {
            $query->where('class_id', $class_id);
        }])->whereHas('registrations', function ($query) use ($class_id) {
            $query->where('class_id', $class_id);
        })->findOrFail($student_id);

        return response()->json([
            'status' => true,
            'message' => 'student retrieved successfully',
            'data' => $student,
        ]);
    }
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Student $student, string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $student = Student::findOrFail($id);
        $this->authorize('update', $student);

        $student->update($request->validate([
            'father_name' => 'required|string',
            'father_phone' => ['required','regex:/^(\+98)(9)[0-9]{9}$/'],
            'mother_name' => 'required|string',
            'mother_phone' => ['required','regex:/^(\+98)(9)[0-9]{9}$/'],
        ]));

        if($student)
        {
            return response()->json([
                'status' => true,
                'message' => 'student updated successfully',
                'data' => $student
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while updating student'
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try{
            $student = Student::findOrFail($id);
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'Student not found',
                'error' => $e->getMessage()
            ]);
        }

        $result = $this->accounts->deleteStudentProfile($student);

        if($result)
        {
            return response()->json([
                'status' => true,
                'message' => 'Student deleted successfully.',
                'error' => null
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while deleting the Student.',
                'error' => null
            ]);
        }
    }
}

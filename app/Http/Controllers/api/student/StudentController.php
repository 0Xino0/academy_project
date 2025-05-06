<?php

namespace App\Http\Controllers\api\student;

use App\Models\User;
use App\Models\Student;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\StudentRequest;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(User $user)
    {
        $students = $user::with('roles')->get()->filter(
            fn ($user) => $user->roles->where('name','student')->toArray()
        );

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
        try{
            $user = User::findOrFail($id);

            return response()->json([
                'status' => true,
                'message' => 'student retrieved successfully',
                'data' => $user
            ]);
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'student not found',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StudentRequest $request )
    {
        $request->validated();
        $student = Student::create([
            'user_id' => $request->user_id,
            'parent1_name' => $request->parent1_name,
            'parent1_phone' => $request->parent1_phone,
            'parent2_name' => $request->parent2_name,
            'parent2_phone' => $request->parent2_phone
        ]);

        if($student)
        {
            return response()->json([
                'status' => true,
                'message' => 'student created successfully',
                'data' => $student
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while creating the student.',
            ]);
        }
            
    
    }

    /**
     * Display the specified resource.
     */
    public function show(Student $student)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Student $student, string $id)
    {
        try{
            $user = $student->findOrFail($id);
            return response()->json([
                'status' => true,
                'message' => 'student retrieved successfully',
                'data' => $user
            ]);
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'no student found',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try{
            $student = Student::findOrFail($id);
            
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'studnet not found',
                'error' => $e->getMessage()
            ]);
        }

        $student->update($request->validate([
            'parent1_name' => 'required|string',
            'parent1_phone' => ['required','regex:/^(0|\+98)[0-9]{10}$/'],
            'parent2_name' => 'required|string',
            'parent2_phone' => ['required','regex:/^(0|\+98)[0-9]{10}$/'],
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
            $user = User::findOrFail($student->user_id);
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'Student not found',
                'error' => $e->getMessage()
            ]);
        }

        $user->removeRole('student');

        $result = $student->delete();

        if($result)
        {
            return response()->json([
                'status' => true,
                'message' => 'Student deleted successfully.',
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while deleting the Student.',
            ]);
        }
    }
}

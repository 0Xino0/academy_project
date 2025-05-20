<?php

namespace App\Http\Controllers\api;

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
        $students = $student::with(['user','class','registration'])
                            ->whereHas('registration',function($query) use ($class_id){
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
        $request->validated();
        $student = Student::create([
            'user_id' => $request->user_id,
            'father_name' => $request->father_name,
            'father_phone' => $request->father_phone,
            'mother_name' => $request->mother_name,
            'mother_phone' => $request->mother_phone
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

    public function show(Student $student , string $id)
    {
        try{
            $student = $student->with('user')->findOrFail($id);
            return response()->json([
                'status' => true,
                'message' => 'student retrieved successfully',
                'data' => $student
            ]);
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'student not found',
                'error' => $e->getMessage()
            ]);
        }
    }

    public function showPerClass(Student $student , string $class_id, string $student_id)
    {
        try{
            $student = $student->with(['user','class','registration'])
                                ->whereHas('registration',function($query) use ($class_id){
                                    $query->where('class_id',$class_id);
                                })
                                ->findOrFail($student_id);
            return response()->json([
                'status' => true,
                'message' => 'student retrieved successfully',
                'data' => $student
            ]);
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'student not found',
                'error' => $e->getMessage()
            ]);
        }
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

<?php

namespace App\Http\Controllers\api\teacher;

use App\Models\User;
use App\Models\Teacher;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherRequest;
use Dotenv\Util\Str;

class TeacherController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(User $user)
    {
        $teachers = $user::with('roles')->get()->filter(
            fn ($user) => $user->roles->where('name', 'teacher')->toArray()
        );
        if($teachers->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No teachers found',
            ]);
        }else{
            return response()->json([
                'status' => true,
                'message' => 'Teachers retrieved successfully',
                'data' => $teachers
            ]);
        }
        
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(string $id,User $user)
    {
        try{
            $teacher = $user::findOrFail($id);
            return response()->json([
                'status' => true,
                'message' => 'Teacher retrieved successfully',
                'data' => $teacher
            ]);

        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'Teacher not found',
                'error' => $e->getMessage()
            ]);
        }
        
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TeacherRequest $request)
    {
        
        // return response()->json([
        //     'data' => $request
        // ]);
        $teacher = Teacher::create($request->validated());

        if($teacher)
        {
            return response()->json([
                'status' => true,
                'message' => 'Teacher information completed successfully.',
                'data' => $teacher
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while creating the teacher.',
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Teacher $teacher)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Teacher $teacher,string $id)
    {
        try{
            $teacher = Teacher::findOrFail($id);
            return response()->json([
                'status' => true,
                'message' => 'Teacher retrieved successfully',
                'data' => $teacher
            ]);

        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'Teacher not found',
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
            $teacher = Teacher::findOrFail($id);
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'Teacher not found',
                'error' => $e->getMessage()
            ]);
        }
        $request->validate([
            'salary' => 'required|integer',
            'resume' => 'nullable|string',
            'join_date' => 'required|date',
            'leave_date' => 'nullable|date'
        ]);

        $teacher->update($request->all());

        if($teacher)
        {
            return response()->json([
                'status' => true,
                'message' => 'Teacher information updated successfully.',
                'data' => $teacher
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while updating the teacher.',
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try{
            $teacher = Teacher::findOrFail($id);
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'Teacher not found',
                'error' => $e->getMessage()
            ]);
        }

        $result = $teacher->delete();

        if($result)
        {
            return response()->json([
                'status' => true,
                'message' => 'Teacher deleted successfully.',
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while deleting the teacher.',
            ]);
        }
        
    }   
}

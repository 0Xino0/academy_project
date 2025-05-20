<?php

namespace App\Http\Controllers\api;

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
    public function index(Teacher $teacher)
    {
        $teachers = $teacher::with('user')->get();
        
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
        //
    }

    // Updating data from the teacher that the admin must have access to
    public function updateAdminInfo(Request $request,string $id)
    {
        try{
            $teacher = Teacher::findOrFail($id);
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'Teacher not found',
            ]);
        }

        $result = $teacher->update($request->validate([
            'salary' => 'required|integer',
        ]));

        if($result)
        {
            return response()->json([
                'status' => true,
                'message' => 'Teacher information updated successfully.',
                'data' =>  $teacher->with('user')->where('id',$id)->first()
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while updating the teacher.',
            ]);
        }

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(TeacherRequest $request)
    {
        // 
    }

    /**
     * Display the specified resource.
     */
    public function show(Teacher $teacher,string $id)
    {
        try{
            $teacher = $teacher::with('user')->where('id',$id)->first();
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
     * Show the form for editing the specified resource.
     */
    public function edit(Teacher $teacher,string $id)
    {
        //
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
        

        $result = $teacher->update($request->validate([
            'resume' => 'nullable|string',
            'bio' => 'nullable|string',
            'degree' => 'nullable|string',
        ]));

        if($result)
        {
            return response()->json([
                'status' => true,
                'message' => 'Teacher information updated successfully.',
                'data' => $teacher->with('user')->where('id',$id)->first()
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
            $user = User::findOrFail($teacher->user_id);
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'Teacher not found',
                'error' => $e->getMessage()
            ]);
        }

        $user->removeRole('teacher');

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

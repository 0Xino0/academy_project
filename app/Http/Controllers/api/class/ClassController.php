<?php

namespace App\Http\Controllers\api\class;
use App\Models\ClassModel;

use Illuminate\Http\Request;
use App\Http\Requests\ClassRequest;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Teacher;
use App\Models\User;

class ClassController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Course $course, Teacher $teacher , ClassModel $classModel)
    {
        try{
            $classes = ClassModel::with(['course', 'teacher.user'])->get();

            return response()->json([
                'status' => true,
                'message' => 'Data fetched successfully',
                'classes' => $classes,
            ]);

        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'Error fetching data',
                'error' => $e->getMessage(),
            ], 500);
        } 
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        try{
            $teacher = Teacher::with('user')->get();
            $course = Course::get();

            return response()->json([
                'status' => true,
                'message' => 'Data fetched successfully',
                'teacher' => $teacher,
                'course' => $course,
            ]);

        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'Error fetching data',
                'error' => $e->getMessage(),
            ], 500);
        }
        
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ClassRequest $request)
    {
        try{
            $class = ClassModel::create($request->validated());

            return response()->json([
                'status' => true,
                'message' => 'Class created successfully',
                'class' => $class,
            ]);

        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'Error creating class',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ClassModel $classModel)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        try{
            $class = ClassModel::with(['course', 'teacher.user'])->findOrFail($id);
            $teachers = Teacher::with('user')->get();
            $courses = Course::get();

            return response()->json([
                'status' => true,
                'message' => 'Data fetched successfully',
                'class' => $class,
                'teachers' => $teachers,
                'courses' => $courses,
            ]);

        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'Error fetching data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ClassRequest $request, string $id)
    {
        try{
            $class = ClassModel::findOrFail($id);
            $class->update($request->validated());

            return response()->json([
                'status' => true,
                'message' => 'Class updated successfully',
                'class' => $class,
            ]);

        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'Error updating class',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try{
            $class = ClassModel::findOrFail($id);
            $class->delete();

            return response()->json([
                'status' => true,
                'message' => 'Class deleted successfully',
            ]);

        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'Error deleting class',
                'error' => $e->getMessage(),
            ], 500);
        }
    
    }
}

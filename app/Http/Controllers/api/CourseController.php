<?php

namespace App\Http\Controllers\api;
use App\Http\Controllers\Controller;

use App\Models\Course;
use Illuminate\Http\Request;
use App\Http\Requests\CourseRequest;

class CourseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try{
            $courses = Course::get();
            return response()->json([
                'status' => true,
                'message' => 'data retrivied successfully',
                'data' => $courses
            ]);
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'an error while getting courses data',
                'error' => $e->getMessage()
            ]);
        }
        
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CourseRequest $request)
    {
        
        $course = Course::create($request->validated());

        if($course)
        {
            return response()->json([
                'status' => true,
                'message' => 'course created successfully',
                'data' => $course
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => 'an error while creating courses',
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try{
            $course = Course::findOrFail($id);
            return response()->json([
                'status' => true,
                'message' => 'data of course retrivied successfully',
                'data' => $course
            ]);
        }catch(\Exception $e){
            return response()->json([
                'stauts' => false,
                'message' => 'an error while getting courses',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
       //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CourseRequest $request, string $id)
    {
        try{
            $course = Course::findOrFail($id);
        }catch(\Exception $e){
            return response()->json([
                'stauts' => false,
                'message' => 'an error while getting courses',
                'error' => $e->getMessage()
            ]);
        }

        $course->update($request->validated());

        if($course)
        {
            return response()->json([
                'status' => true,
                'message' => 'course updated successfully',
                'data' => $course
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => 'an error while updating course'
            ]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try{
            $course = Course::findOrFail($id);
        }catch(\Exception $e){
            return response()->json([
                'stauts' => false,
                'message' => 'an error while getting courses',
                'error' => $e->getMessage()
            ]);
        }

        $result = $course->delete();
        if($result)
        {
            return response()->json([
                'stauts' => true,
                'message' => 'the course deleted successfully'
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => 'an error while deleting course'
            ]);
        }
    }
}

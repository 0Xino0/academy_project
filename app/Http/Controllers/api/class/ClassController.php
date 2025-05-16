<?php

namespace App\Http\Controllers\api\class;
use App\Models\Term;

use App\Models\User;
use App\Models\Course;
use App\Models\Teacher;
use App\Models\ClassModel;
use Illuminate\Http\Request;
use App\Http\Requests\ClassRequest;
use App\Http\Controllers\Controller;

class ClassController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(string $term_id)
    {
        try{
            $classes = ClassModel::with(['course', 'teacher.user','term'])->where('term_id',$term_id)->get();

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
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ClassRequest $request,string $term_id)
    {
        $data = array_merge($request->validated(),['term_id' => $term_id]);

        try{
            // Get the term dates
            $term = Term::findOrFail($term_id);
            
            // Check if class dates are within term dates
            if ($data['start_date'] < $term->start_date || $data['end_date'] > $term->end_date) {
                return response()->json([
                    'status' => false,
                    'message' => 'Class dates must be within the term dates',
                    'term_dates' => [
                        'start_date' => $term->start_date,
                        'end_date' => $term->end_date
                    ]
                ], 422);
            }

            // check class status is active or not
            if($term->is_active == 0){
                return response()->json([
                    'status' => false,
                    'message' => 'Term is not active',
                ], 422);
            }

            $class = ClassModel::create($data);

            return response()->json([
                'status' => true,
                'message' => 'Class created successfully',
                'class' => $class->with('course','teacher','term')->where('id',$class->id)->first(),
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
    public function show(string $term_id,string $class_id)
    {
        try{
            $class = ClassModel::with(['course', 'teacher.user','term'])->where('id',$class_id)->where('term_id',$term_id)->first();

            return response()->json([
                'status' => true,
                'message' => 'Data fetched successfully',
                'class' => $class
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
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ClassRequest $request, string $term_id,string $class_id)
    {
        try{
            $class = ClassModel::with(['course', 'teacher.user','term'])->where('id',$class_id)->where('term_id',$term_id)->first();
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
    public function destroy(string $term_id,string $class_id)
    {
        try{
            $class = ClassModel::with(['course', 'teacher.user','term'])->where('id',$class_id)->where('term_id',$term_id)->first();
            $result =  $class->delete();

            if($result){
                return response()->json([
                    'status' => true,
                    'message' => 'Class deleted successfully',
                ]);
            }

        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'Error deleting class',
                'error' => $e->getMessage(),
            ], 500);
        }
    
    }
}

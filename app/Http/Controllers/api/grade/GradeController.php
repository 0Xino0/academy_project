<?php

namespace App\Http\Controllers\api\grade;

use App\Models\Grade;
use App\Models\ClassModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\GradeRequest;
use App\Http\Controllers\Controller;
use App\customs\services\GradeService;

class GradeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(string $class_id)
    {
        $grades = Grade::with('student.user')->where('class_id', $class_id)->get();
        return response()->json([
            'status' => true,
            'message' => 'Grades fetched successfully',
            'grades' => $grades,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(string $class_id)
    {
        
        
    }

    /**
     * Store a newly created resource in storage.
     */
    public function storeOrUpdate(GradeRequest $request, $class_id)
    {
        try{
            $grade = Grade::updateOrCreate(
                [
                    'student_id' => $request->student_id,
                    'class_id' => $class_id
                ],
                ['grade' => $request->grade]
            );
            return response()->json([
                'status' => true,
                'message' => 'Grade stored successfully',
                'grade' => $grade,
            ]);
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'Error storing grade',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

        public function batchStoreOrUpdate(Request $request, $class_id)
{
    $request->validate([
        'grades' => 'required|array|min:1',
        'grades.*.student_id' => 'required|exists:students,id',
        'grades.*.grade' => 'required|numeric|min:0|max:20'
    ]);

    DB::beginTransaction();
    try {
        foreach ($request->grades as $entry) {
            Grade::updateOrCreate(
                [
                    'student_id' => $entry['student_id'],
                    'class_id' => $class_id
                ],
                [
                    'grade' => $entry['grade']
                ]
            );
        }

        DB::commit();

        return response()->json([
            'status' => true,
            'message' => 'Grades successfully submitted.',
        ]);
    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'status' => false,
            'message' => 'Failed to submit grades.',
            'error' => $e->getMessage()
        ], 500);
    }
}


    /**
     * Display the specified resource.
     */
    public function showForStudent(string $class_id)
    {
        
        try{
            $student_id = auth()->user()->student->id;
            
            $grade = Grade::with('student.user')
                            ->where('class_id', $class_id)
                            ->where('student_id', $student_id)
                            ->first();
            if(!$grade){
                return response()->json([
                    'status' => false,
                    'message' => 'Grade not found',
                ], 404);
            }
            return response()->json([
                'status' => true,
                'message' => 'Grade fetched successfully',
                'grade' => $grade,
            ]);
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'Error fetching grade',
                'error' => $e->getMessage(),
            ], 500);
        }
        
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Grade $grade)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Grade $grade)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Grade $grade)
    {
        //
    }
}

<?php

namespace App\Http\Controllers\api\registration;

use App\Models\Student;
use App\Models\ClassModel;
use App\Models\Registration;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class RegistrationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(string $term_id,string $class_id)
    {
        $registrations = Registration::with([
            'student:id,user_id',
            'student.user:id,first_name,last_name,national_id',
            'class:id,name,start_date,end_date,capacity,tuition_fee,teacher_id,course_id,term_id',
            'class.course:id,title,level',
            'class.teacher:id,user_id',
            'class.teacher.user:id,first_name,last_name,national_id',
            'class.term:id,year,season,is_active'
        ])
        ->whereHas('class', function($query) use ($term_id) {
            $query->where('term_id', $term_id);
        })
        ->where('class_id', $class_id)
        ->get();

        return response()->json([
            'registrations' => $registrations
        ]);
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
    public function store(Request $request,string $term_id,string $class_id)
    {
        
        $student_id = auth()->user()->student->id;

        // Check if the class belongs to the specified term
        $class = ClassModel::where('id', $class_id)
            ->where('term_id', $term_id)
            ->first();

        if (!$class) {
            return response()->json([
                'message' => 'This class does not belong to the specified term',
            ], 409);
        }

        // Check if the student is already registered in the class
        $existingRegistration = Registration::where('student_id', $request->student_id)
            ->where('class_id', $class_id)
            ->first();
        if ($existingRegistration) {
            return response()->json([
                'message' => 'Student is already registered in this class',
            ], 409);
        }
        // Check if the class is full
        if ($class->registeredStudents()->count() >= $class->capacity) {
            return response()->json([
                'message' => 'Class is full',
            ], 409);
        }
            
         // Check if the class is already started
         if ($class->start_date <= now()) {
             return response()->json([
                 'message' => 'Class has already started',
             ], 409);
         }

        // Check if the student is already registered in another class at the same time
        $overlappingClass = Registration::where('student_id', $request->student_id)
        ->where('class_id', '!=', $class_id)
        ->whereHas('class', function ($query) use ($class) {
            $query->whereBetween('start_date', [$class->start_date, $class->end_date])
                  ->orWhereBetween('end_date', [$class->start_date, $class->end_date]);
        })
        ->exists();
        if ($overlappingClass) {
            return response()->json([
                'message' => 'Student is already registered in another class at the same time',
            ], 409);
        }
            
        // Create the registration
        try {
            $registration = Registration::create([
                'student_id' => $student_id,
                'class_id' => $class_id,
                'registration_date' => now(),
            ]);
    
            return response()->json([
                'message' => 'Registration created successfully',
                'registration' => $registration,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating registration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $term_id,string $class_id,string $registration_id)
    {
        try{
            $registration = Registration::with([
                'student:id,user_id',
                'student.user:id,first_name,last_name,national_id',
                'class:id,name,start_date,end_date,capacity,tuition_fee,teacher_id,course_id,term_id',
                'class.course:id,title,level',
                'class.teacher:id,user_id',
                'class.teacher.user:id,first_name,last_name',
                'class.term:id,year,season,is_active'
            ])
            ->whereHas('class', function($query) use ($term_id) {
                $query->where('term_id', $term_id);
            })
            ->where('class_id', $class_id)
            ->where('id', $registration_id)
            ->first();

        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'error while finding registration information',
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'find registration information successfully',
            'data' => $registration
        ]);
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
    public function update(Request $request, string $term_id,string $class_id,string $registration_id)
    {
        $student_id = auth()->user()->student->id;

        try{
            $registration = Registration::findOrFail($registration_id);

        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'error while finding registration information',
                'error' => $e->getMessage()
            ]);
        }

        // Check if the class belongs to the specified term
        $class = ClassModel::where('id', $class_id)
            ->where('term_id', $term_id)
            ->first();

        if (!$class) {
            return response()->json([
                'message' => 'This class does not belong to the specified term',
            ], 409);
        }

        // Check if the student is already registered in the class
        $existingRegistration = Registration::where('student_id', $student_id)
            ->where('class_id', $class_id)
            ->first();
        if ($existingRegistration) {
            return response()->json([
                'message' => 'Student is already registered in this class',
            ], 409);
        }
        // Check if the class is full
        $class = ClassModel::find($class_id);
        if ($class->registeredStudents()->count() >= $class->capacity) {
            return response()->json([
                'message' => 'Class is full',
            ], 409);
        }
            
         // Check if the class is already started
         if ($class->start_date <= now()) {
             return response()->json([
                 'message' => 'Class has already started',
             ], 409);
        }

        // Check if the student is already registered in another class at the same time
        $overlappingClass = Registration::where('student_id', $student_id)
        ->where('class_id', '!=', $class_id)
        ->whereHas('class', function ($query) use ($class) {
            $query->whereBetween('start_date', [$class->start_date, $class->end_date])
                  ->orWhereBetween('end_date', [$class->start_date, $class->end_date]);
        })
        ->exists();
        if ($overlappingClass) {
            return response()->json([
                'message' => 'Student is already registered in another class at the same time',
            ], 409);
        }

        try{
            $registration->update([
                    'student_id' => $student_id,
                    'class_id' => $class_id,
                    'registration_date' => now(),
                ]);

            return response()->json([
                'status' => true,
                'message' => 'registration update successfully',
                'data' => $registration
            ]);

        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'error while updating registration information',
                'error' => $e->getMessage()
            ]);
        }
        
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $term_id,string $class_id,string $registration_id)
    {
        try{
            $registration = Registration::findOrFail($registration_id);

        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'error while finding registration information',
                'error' => $e->getMessage()
            ]);
        }

        $result = $registration->delete();

        if($result)
        {
            return response()->json([
                'status' => true,
                'message' => 'registration deleted successfully'
            ]);
        }else{
            return response()->json([
                'status' => false,
                'message' => 'error while deleting registration'
            ]);
        }
        
    }
}

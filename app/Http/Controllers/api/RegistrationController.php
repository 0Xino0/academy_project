<?php

namespace App\Http\Controllers\api;

use App\Models\Student;
use App\Models\ClassModel;
use App\Models\Registration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        // Check if current time is within registration period
        if (now() < $class->startRegistration_date || now() > $class->endRegistration_date) {
            return response()->json([
                'status' => false,
                'message' => 'Registration period has ended or has not started yet',
            ], 409);
        }

        // Check if the student is already registered in the class
        $existingRegistration = Registration::where('student_id', $student_id)
            ->where('class_id', $class_id)
            ->first();
        if ($existingRegistration) {
            return response()->json([
                'status' => false,
                'message' => 'You are already registered in this class',
            ], 409);
            
        }

        // Check if the class is full
        if ($class->registeredStudents()->count() >= $class->capacity) {
            return response()->json([
                'status' => false,
                'message' => 'Class is full',
            ], 409);
        }
            
        // Check if the class is already started
        if ($class->start_date <= now()) {
            return response()->json([
                'status' => false,
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
                'message' => 'You are already registered in another class at the same time',
            ], 409);
        }
            
        // Create the registration
        try {
            DB::beginTransaction();
    
            // create registration
            $registration = Registration::create([
                'student_id' => $student_id,
                'class_id' => $class_id,
            ]);
    
            // create debt
            $registration->debt()->create([
                'total_amount' => $class->tuition_fee,
                'paid_amount' => 0,
                'is_paid' => false,
            ]);
    
            DB::commit();
    
            return response()->json([
                'message' => 'Registration and debt created successfully',
                'registration' => $registration,
                'debt' => $registration->load('debt')
            ], 201);
    
        } catch (\Exception $e) {
            DB::rollBack();
    
            return response()->json([
                'message' => 'Error during registration',
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
        //
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

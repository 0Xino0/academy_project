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
    public function index()
    {
        $registrations = Registration::with([
            'student:id,user_id',
            'student.user:id,first_name,last_name,national_id',
            'class:id,name,start_date,end_date,capacity,tuition_fee,teacher_id,course_id',
            'class.course:id,title,level',
            'class.teacher:id,user_id',
            'class.teacher.user:id,first_name,last_name'
        ])->get();

        return response()->json([
            'registrations' => $registrations
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $classes = ClassModel::with([
            'course:id,title,level', 
            'teacher:id,user_id',
            'teacher.user:id,first_name,last_name',
            ])->get();

        $students = Student::with([
            'user:id,first_name,last_name,national_id'
        ])->get(['id','user_id']);    
        
        
        return response()->json([
            'classes' => $classes,
            'students' => $students
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        

        $request->validate([
            'student_id' => 'required|exists:students,id',
            'class_id' => 'required|exists:classes,id',
            'registration_date' => 'required|date'
        ]);
        // Check if the student is already registered in the class
        $existingRegistration = Registration::where('student_id', $request->student_id)
            ->where('class_id', $request->class_id)
            ->first();
        if ($existingRegistration) {
            return response()->json([
                'message' => 'Student is already registered in this class',
            ], 409);
        }
        // Check if the class is full
        $class = ClassModel::find($request->class_id);
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
        ->where('class_id', '!=', $request->class_id)
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
                'student_id' => $request->student_id,
                'class_id' => $request->class_id,
                'registration_date' => $request->registration_date,
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
    public function show(string $id)
    {
        try{
            $registration = Registration::with([
                'student:id,user_id',
                'student.user:id,first_name,last_name,national_id',
                'class:id,name,start_date,end_date,capacity,tuition_fee,teacher_id,course_id',
                'class.course:id,title,level',
                'class.teacher:id,user_id',
                'class.teacher.user:id,first_name,last_name'
            ])->findOrFail($id);

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
        try{
            $registration = Registration::with([
                'student:id,user_id',
                'student.user:id,first_name,last_name,national_id',
                'class:id,name,start_date,end_date,capacity,tuition_fee,teacher_id,course_id',
                'class.course:id,title,level',
                'class.teacher:id,user_id',
                'class.teacher.user:id,first_name,last_name'
            ])->findOrFail($id);

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
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'registration_date' => 'required|date',
            'class_id' => 'required|exists:classes,id',
            'student_id' => 'required|exists:students,id'
        ]);

        try{
            $registration = Registration::findOrFail($id);

        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'error while finding registration information',
                'error' => $e->getMessage()
            ]);
        }

        // Check if the student is already registered in the class
        $existingRegistration = Registration::where('student_id', $request->student_id)
            ->where('class_id', $request->class_id)
            ->first();
        if ($existingRegistration) {
            return response()->json([
                'message' => 'Student is already registered in this class',
            ], 409);
        }
        // Check if the class is full
        $class = ClassModel::find($request->class_id);
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
        ->where('class_id', '!=', $request->class_id)
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
            $registration->update($request->all());

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
    public function destroy(string $id)
    {
        try{
            $registration = Registration::findOrFail($id);

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

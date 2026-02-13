<?php

namespace App\Http\Controllers\api;

use App\Models\Schedule;
use App\Models\ClassModel;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleRequest;

class ScheduleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($term_id)
    {
        try {
            $schedules = Schedule::with(['class'])
                    ->whereHas('class', function($query) use ($term_id) {
                        $query->where('term_id', $term_id);
                    })
                    ->get();

            return response()->json([
                'status' => true,
                'message' => 'Schedules fetched successfully',
                'schedules' => $schedules
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching schedules',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function indexForTeacher($term_id)
    {
        try{
            $teacher_id = auth()->user()->teacher->id;
            $schedules = Schedule::with(['class'])
                    ->whereHas('class', function($query) use ($teacher_id) {
                        $query->where('teacher_id', $teacher_id);
                    })
                    ->whereHas('class', function($query) use ($term_id) {
                        $query->where('term_id', $term_id);
                    })
                    ->get();

            if($schedules->isEmpty()){
                return response()->json([
                    'status' => false,
                    'message' => 'No schedules found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Schedules fetched successfully',
                'schedules' => $schedules
            ]);
                    
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'Error fetching teacher id',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function indexForStudent($term_id)
    {
        try{
            $student_id = auth()->user()->student->id;
            $schedules = Schedule::with(['class'])
                    ->whereHas('class.registrations', function($query) use ($student_id) {
                        $query->where('student_id', $student_id);
                    })
                    ->whereHas('class', function($query) use ($term_id) {
                        $query->where('term_id', $term_id);
                    })
                    ->get();
                    
            if($schedules->isEmpty()){
                return response()->json([
                    'status' => false,
                    'message' => 'No schedules found'
                ], 404);
            }
                    
            return response()->json([
                'status' => true,
                'message' => 'Schedules fetched successfully',
                'schedules' => $schedules
            ]);
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'Error fetching student id',
                'error' => $e->getMessage()
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
    public function store(ScheduleRequest $request, $term_id, $class_id)
    {
        try {
            // First verify that the class belongs to the specified term
            $class = ClassModel::where('id', $class_id)
                ->where('term_id', $term_id)
                ->first();


            if (!$class) {
                return response()->json([
                    'status' => false,
                    'message' => 'This class does not belong to the specified term'
                ], 422);
            }

            // Check for schedule overlaps within the same term
            $overlappingSchedule = Schedule::whereHas('class', function($query) use ($term_id) {
                    $query->where('term_id', $term_id);
                })
                ->where('day_of_week', $request->day_of_week)
                ->where(function ($query) use ($request) {
                    $query->whereBetween('start_time', [$request->start_time, $request->end_time])
                        ->orWhereBetween('end_time', [$request->start_time, $request->end_time])
                        ->orWhere(function ($q) use ($request) {
                            $q->where('start_time', '<=', $request->start_time)
                                ->where('end_time', '>=', $request->end_time);
                        });
                })
                ->first();

            if ($overlappingSchedule) {
                return response()->json([
                    'status' => false,
                    'message' => 'Schedule overlaps with another class schedule in the same term',
                    'overlapping_schedule' => $overlappingSchedule
                ], 422);
            }

            // Create the new schedule
            $schedule = Schedule::create([
                'class_id' => $class_id,
                'day_of_week' => $request->day_of_week,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Schedule created successfully',
                'schedule' => $schedule->with('class')->where('id', $schedule->id)->first()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error creating schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Schedule $schedule)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Schedule $schedule)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ScheduleRequest $request, $term_id, $class_id, $schedule_id)
    {
        try {
            // First verify that the class belongs to the specified term
            $class = ClassModel::where('id', $class_id)
                ->where('term_id', $term_id)
                ->first();

            if (!$class) {
                return response()->json([
                    'status' => false,
                    'message' => 'This class does not belong to the specified term'
                ], 422);
            }

            // Find the schedule to update
            $schedule = Schedule::where('id', $schedule_id)
                ->whereHas('class', function($query) use ($class_id, $term_id) {
                    $query->where('id', $class_id);
                    $query->where('term_id', $term_id);
                })
                ->first();

            if (!$schedule) {
                return response()->json([
                    'status' => false,
                    'message' => 'Schedule not found'
                ], 404);
            }

            // Check for schedule overlaps within the same term, excluding the current schedule
            $overlappingSchedule = Schedule::whereHas('class', function($query) use ($term_id) {
                    $query->where('term_id', $term_id);
                })
                ->where('id', '!=', $schedule_id) // Exclude current schedule
                ->where('day_of_week', $request->day_of_week)
                ->where(function ($query) use ($request) {
                    $query->whereBetween('start_time', [$request->start_time, $request->end_time])
                        ->orWhereBetween('end_time', [$request->start_time, $request->end_time])
                        ->orWhere(function ($q) use ($request) {
                            $q->where('start_time', '<=', $request->start_time)
                                ->where('end_time', '>=', $request->end_time);
                        });
                })
                ->first();

            if ($overlappingSchedule) {
                return response()->json([
                    'status' => false,
                    'message' => 'Schedule overlaps with another class schedule in the same term',
                    'overlapping_schedule' => $overlappingSchedule
                ], 422);
            }

            // Update the schedule
            $schedule->update([
                'day_of_week' => $request->day_of_week,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Schedule updated successfully',
                'schedule' => $schedule
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error updating schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($term_id, $class_id, $schedule_id)
    {
        try{
            $schedule = Schedule::where('id', $schedule_id)
                ->whereHas('class', function($query) use ($class_id, $term_id) {
                    $query->where('id', $class_id);
                    $query->where('term_id', $term_id);
                })
                ->first();

            if(!$schedule){
                return response()->json([
                    'status' => false,
                    'message' => 'Schedule not found',
                    'schedule' => null
                ], 404);
            }

            $schedule->delete();

            return response()->json([
                'status' => true,
                'message' => 'Schedule deleted successfully',
                'schedule' => null
            ]);
        }catch(\Exception $e){
            return response()->json([
                'status' => false,
                'message' => 'Error deleting schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

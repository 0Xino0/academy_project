<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CourseRequest;
use App\Models\ClassModel;
use App\Models\Course;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

class CourseController extends Controller
{
    public function index()
    {
        try {
            return response()->json([
                'status' => true,
                'message' => 'Courses retrieved successfully',
                'data' => Course::all(),
            ]);
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    public function store(CourseRequest $request)
    {
        try {
            $course = Course::create($request->validated());

            return response()->json([
                'status' => true,
                'message' => 'Course created successfully',
                'data' => $course,
            ]);
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    public function show(string $id)
    {
        try {
            $course = Course::findOrFail($id);

            return response()->json([
                'status' => true,
                'message' => 'Course retrieved successfully',
                'data' => $course,
            ]);
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    public function update(CourseRequest $request, string $id)
    {
        try {
            $course = Course::findOrFail($id);
            $course->update($request->validated());

            return response()->json([
                'status' => true,
                'message' => 'Course updated successfully',
                'data' => $course,
            ]);
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    public function destroy(string $id)
    {
        try {
            $course = Course::findOrFail($id);
            if (! $course->delete()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Course could not be deleted',
                ], 500);
            }

            return response()->json([
                'status' => true,
                'message' => 'Course deleted successfully',
            ]);
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    public function getActiveCourses()
    {
        try {
            $classes = ClassModel::with(['course', 'teacher.user', 'schedules'])
                ->whereHas('term', function ($query) {
                    $query->where('is_active', true);
                })
                ->where('endRegistration_Date', '>=', now()->toDateString())
                ->withCount('registrations')
                ->get();

            $data = $classes->map(function ($class) {
                $teacher = $class->teacher?->user;

                return [
                    'id' => $class->id,
                    'term_id' => $class->term_id,
                    'tuition_fee' => $class->tuition_fee,
                    'course_name' => $class->course->title,
                    'teacher_name' => $teacher ? trim($teacher->first_name . ' ' . $teacher->last_name) : null,
                    'capacity' => $class->capacity,
                    'registered_students_count' => $class->registrations_count,
                    'schedules' => $class->schedules->map(function ($schedule) {
                        return [
                            'day_of_week' => $schedule->day_of_week,
                            'start_time' => $schedule->start_time,
                            'end_time' => $schedule->end_time,
                        ];
                    }),
                ];
            });

            return response()->json([
                'status' => true,
                'message' => 'Active courses retrieved successfully',
                'data' => $data,
            ]);
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    private function failure(Throwable $e)
    {
        if ($e instanceof ModelNotFoundException) {
            return response()->json([
                'status' => false,
                'message' => 'Course not found',
            ], 404);
        }

        report($e);

        return response()->json([
            'status' => false,
            'message' => 'Unable to process the course request',
        ], 500);
    }
}

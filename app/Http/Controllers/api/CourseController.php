<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CourseRequest;
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
            app(\App\Services\AcademicDeletionService::class)->deleteCourse($id);

            return response()->json([
                'status' => true,
                'message' => 'Course deleted successfully',
            ]);
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    public function getActiveCourses(\App\Services\RegistrationCatalogue $catalogue)
    {
        try {
            return response()->json($catalogue->response(auth()->user()));
        } catch (Throwable $e) {
            return $this->failure($e);
        }
    }

    private function failure(Throwable $e)
    {
        if ($e instanceof \Illuminate\Validation\ValidationException) throw $e;
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

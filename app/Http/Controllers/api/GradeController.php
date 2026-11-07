<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BatchGradeRequest;
use App\Http\Requests\GradeRequest;
use App\Models\ClassModel;
use App\Models\Grade;
use App\Models\Student;
use App\Services\GradeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Throwable;

class GradeController extends Controller
{
    public function __construct(private GradeService $grades) {}

    public function index(string $class_id)
    {
        return $this->respond(function () use ($class_id) {
            $class = ClassModel::withTrashed()->findOrFail($class_id);
            $this->authorize('viewGrades', $class);
            $students = Student::with('user')->whereHas('registrations', fn ($query) => $query->where('class_id', $class->id))
                ->orderBy('id')->get();
            return response()->json([
                'status' => true, 'message' => 'Grades fetched successfully',
                'class' => $class->load(['course', 'term', 'teacher.user'])->loadCount('registrations'),
                'students' => $students,
                'grades' => Grade::with('student.user')->where('class_id', $class->id)->orderBy('student_id')->get(),
            ]);
        });
    }

    public function storeOrUpdate(GradeRequest $request, string $class_id)
    {
        return $this->respond(fn () => response()->json([
            'status' => true, 'message' => 'Grade stored successfully',
            'grade' => $this->grades->save($request->user(), $class_id, [$request->validated()])->first(),
        ]));
    }

    public function batchStoreOrUpdate(BatchGradeRequest $request, string $class_id)
    {
        return $this->respond(fn () => response()->json([
            'status' => true, 'message' => 'Grades successfully submitted.',
            'grades' => $this->grades->save($request->user(), $class_id, $request->validated('grades')),
        ]));
    }

    public function showForStudent(string $class_id)
    {
        return $this->respond(function () use ($class_id) {
            $class = ClassModel::withTrashed()->findOrFail($class_id);
            $this->authorize('viewOwnGrade', $class);
            $grade = Grade::with(['student.user', 'class.teacher.user'])
                ->where('class_id', $class->id)->where('student_id', auth()->user()->student->id)->first();
            return response()->json([
                'status' => true, 'message' => $grade ? 'Grade fetched successfully' : 'A grade has not been recorded yet.',
                'grade' => $grade,
            ]);
        });
    }

    private function respond(callable $action)
    {
        try {
            return $action();
        } catch (ModelNotFoundException | ValidationException | AuthorizationException $error) {
            throw $error;
        } catch (Throwable $error) {
            report($error);
            return response()->json(['status' => false, 'message' => 'Unable to process the grade request.'], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClassRequest;
use App\Models\ClassModel;
use App\Models\Term;
use App\Services\ClassService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Throwable;

class ClassController extends Controller
{
    public function __construct(private ClassService $classes) {}

    public function index(string $term_id)
    {
        return $this->respond(function () use ($term_id) {
            Term::findOrFail($term_id);
            $classes = ClassModel::with(['course', 'teacher.user', 'term'])->withCount('registrations')
                ->where('term_id', $term_id)->get();

            return $this->listing($classes);
        });
    }

    public function indexForStudent(string $term_id)
    {
        return $this->respond(function () use ($term_id) {
            Term::findOrFail($term_id);
            $studentId = auth()->user()->student->id;
            $classes = ClassModel::with(['course', 'teacher.user', 'term',
                'registrations' => fn ($query) => $query->where('student_id', $studentId)])
                ->withCount('registrations')->where('term_id', $term_id)
                ->whereHas('registrations', fn ($query) => $query->where('student_id', $studentId))->get();

            return $this->listing($classes);
        });
    }

    public function indexForTeacher(string $term_id)
    {
        return $this->respond(function () use ($term_id) {
            Term::findOrFail($term_id);
            $classes = ClassModel::with(['course', 'teacher.user', 'term'])->withCount('registrations')
                ->where('term_id', $term_id)->where('teacher_id', auth()->user()->teacher->id)->get();

            return $this->listing($classes);
        });
    }

    public function store(ClassRequest $request, string $term_id)
    {
        return $this->respond(fn () => $this->single(
            $this->classes->save($term_id, $request->validated()), 'Class created successfully'
        ));
    }

    public function show(string $term_id, string $class_id)
    {
        return $this->respond(function () use ($term_id, $class_id) {
            $class = ClassModel::with(['course', 'teacher.user', 'term'])->withCount('registrations')
                ->where('term_id', $term_id)->findOrFail($class_id);

            return $this->single($class);
        });
    }

    public function showForTeacher(string $term_id, string $class_id)
    {
        return $this->respond(function () use ($term_id, $class_id) {
            $class = ClassModel::where('term_id', $term_id)->findOrFail($class_id);
            $this->authorize('viewAsTeacher', $class);

            return $this->single($class->load(['course', 'teacher.user', 'term', 'registrations.student.user'])
                ->loadCount('registrations'));
        });
    }

    public function showForStudent(string $term_id, string $class_id)
    {
        return $this->respond(function () use ($term_id, $class_id) {
            $class = ClassModel::where('term_id', $term_id)->findOrFail($class_id);
            $this->authorize('viewAsStudent', $class);
            $studentId = auth()->user()->student->id;

            return $this->single($class->load(['course', 'teacher.user', 'term',
                'registrations' => fn ($query) => $query->where('student_id', $studentId)])->loadCount('registrations'));
        });
    }

    public function update(ClassRequest $request, string $term_id, string $class_id)
    {
        return $this->respond(fn () => $this->single(
            $this->classes->save($term_id, $request->validated(), $class_id), 'Class updated successfully'
        ));
    }

    public function destroy(string $term_id, string $class_id)
    {
        return $this->respond(function () use ($term_id, $class_id) {
            app(\App\Services\AcademicDeletionService::class)->deleteClass($term_id, $class_id);

            return response()->json(['status' => true, 'message' => 'Class deleted successfully', 'error' => null]);
        });
    }

    private function listing($classes)
    {
        return response()->json(['status' => true, 'message' => 'Data fetched successfully', 'classes' => $classes]);
    }

    private function single(ClassModel $class, string $message = 'Data fetched successfully')
    {
        return response()->json(['status' => true, 'message' => $message, 'class' => $class]);
    }

    private function respond(callable $action)
    {
        try {
            return $action();
        } catch (ModelNotFoundException | ValidationException | AuthorizationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json(['status' => false, 'message' => 'Unable to process the class request'], 500);
        }
    }
}

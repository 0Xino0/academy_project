<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleRequest;
use App\Models\Schedule;
use App\Models\Term;
use App\Services\ScheduleService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Throwable;

class ScheduleController extends Controller
{
    public function __construct(private ScheduleService $schedules) {}

    public function index(string $term_id)
    {
        return $this->respond(function () use ($term_id) {
            Term::findOrFail($term_id);

            return $this->listing(Schedule::with('class')
                ->whereHas('class', fn ($query) => $query->where('term_id', $term_id))->get());
        });
    }

    public function indexForTeacher(string $term_id)
    {
        return $this->respond(function () use ($term_id) {
            Term::findOrFail($term_id);
            $teacherId = auth()->user()->teacher->id;

            return $this->listing(Schedule::with('class')->whereHas('class', function ($query) use ($term_id, $teacherId) {
                $query->where('term_id', $term_id)->where('teacher_id', $teacherId);
            })->get());
        });
    }

    public function indexForStudent(string $term_id)
    {
        return $this->respond(function () use ($term_id) {
            Term::findOrFail($term_id);
            $studentId = auth()->user()->student->id;

            return $this->listing(Schedule::with('class')->whereHas('class', function ($query) use ($term_id, $studentId) {
                $query->where('term_id', $term_id)->whereHas('registrations', fn ($query) => $query->where('student_id', $studentId));
            })->get());
        });
    }

    public function store(ScheduleRequest $request, string $term_id, string $class_id)
    {
        return $this->respond(function () use ($request, $term_id, $class_id) {
            $schedule = $this->schedules->save($term_id, $class_id, $request->validated());

            return response()->json(['status' => true, 'message' => 'Schedule created successfully', 'schedule' => $schedule]);
        });
    }

    public function update(ScheduleRequest $request, string $term_id, string $class_id, string $schedule_id)
    {
        return $this->respond(function () use ($request, $term_id, $class_id, $schedule_id) {
            $schedule = $this->schedules->save($term_id, $class_id, $request->validated(), $schedule_id);

            return response()->json(['status' => true, 'message' => 'Schedule updated successfully', 'schedule' => $schedule]);
        });
    }

    public function destroy(string $term_id, string $class_id, string $schedule_id)
    {
        return $this->respond(function () use ($term_id, $class_id, $schedule_id) {
            $this->schedules->delete($term_id, $class_id, $schedule_id);

            return response()->json(['status' => true, 'message' => 'Schedule deleted successfully', 'schedule' => null]);
        });
    }

    private function listing($schedules)
    {
        return response()->json(['status' => true, 'message' => 'Schedules fetched successfully', 'schedules' => $schedules]);
    }

    private function respond(callable $action)
    {
        try {
            return $action();
        } catch (ModelNotFoundException | ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return response()->json(['status' => false, 'message' => 'Unable to process the schedule request'], 500);
        }
    }
}

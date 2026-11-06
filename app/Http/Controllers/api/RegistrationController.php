<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\HandlesEnrollmentResponses;
use App\Models\ClassModel;
use App\Models\Registration;
use App\Services\RegistrationService;

class RegistrationController extends Controller
{
    use HandlesEnrollmentResponses;

    public function __construct(private RegistrationService $registrations) {}

    public function index(string $term_id, string $class_id)
    {
        return $this->enrollmentResponse(function () use ($term_id, $class_id) {
            $class = ClassModel::where('term_id', $term_id)->findOrFail($class_id);
            $this->authorize('viewStudents', $class);
            $rows = Registration::with(['student.user', 'class.course', 'class.teacher.user', 'class.term', 'debt'])
                ->where('class_id', $class->id);
            if (auth()->user()->hasRole('manager')) $rows->withTrashed();
            return response()->json(['status' => true, 'message' => 'Enrollments retrieved successfully', 'registrations' => $rows->get()]);
        });
    }

    public function store(string $term_id, string $class_id)
    {
        return $this->enrollmentResponse(fn () => response()->json([
            'status' => true, 'message' => 'Enrollment and debt created successfully',
            'registration' => $this->registrations->enroll(auth()->user()->student, $term_id, $class_id),
        ], 201));
    }

    public function show(string $term_id, string $class_id, string $registration_id)
    {
        return $this->enrollmentResponse(function () use ($term_id, $class_id, $registration_id) {
            $row = Registration::withTrashed()->where('class_id', $class_id)
                ->whereHas('class', fn ($query) => $query->where('term_id', $term_id))->findOrFail($registration_id);
            $this->authorize('view', $row);
            return response()->json(['status' => true, 'message' => 'Enrollment retrieved successfully',
                'data' => $row->load(['student.user', 'class.course', 'class.teacher.user', 'class.term', 'debt'])]);
        });
    }

    public function destroy(string $term_id, string $class_id, string $registration_id)
    {
        return $this->enrollmentResponse(fn () => response()->json([
            'status' => true, 'message' => 'Enrollment cancelled; records have been retained.', 'error' => null,
            'registration' => $this->registrations->cancel(auth()->user(), $term_id, $class_id, $registration_id),
        ]));
    }
}

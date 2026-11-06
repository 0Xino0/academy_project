<?php

namespace App\Services;

use App\Exceptions\EnrollmentConflict;
use App\Models\ClassModel;
use App\Models\User;

class RegistrationCatalogue
{
    public function __construct(private EnrollmentRules $rules) {}

    public function response(User $viewer): array
    {
        $student = $viewer->hasRole('student') ? $viewer->student : null;
        $blockedCode = $student ? null : 'student_profile_required';
        $blockedReason = $student ? null : 'Enrollment requires an active student account.';
        if ($student) {
            try { $this->rules->assertNoPreviousDebt($student); }
            catch (EnrollmentConflict $e) { $blockedCode = $e->reason; $blockedReason = $e->getMessage(); }
        }
        $today = now()->toDateString();
        $classes = ClassModel::with(['course', 'teacher.user', 'term', 'schedules'])->withCount('registrations')
            ->whereHas('term', fn ($query) => $query->where('is_active', true))->whereHas('teacher.user')
            ->whereHas('schedules')->where('startRegistration_Date', '<=', $today)
            ->where('endRegistration_Date', '>=', $today)->where('start_date', '>', $today)->get();
        $data = [];
        foreach ($classes as $class) {
            try { $this->rules->assertClassAvailable($class); }
            catch (EnrollmentConflict $e) { continue; }
            $registered = $student && $class->registrations()->where('student_id', $student->id)->exists();
            $reason = $registered ? 'You are already enrolled in this class.' : $blockedReason;
            if ($student && ! $registered && ! $reason) {
                try { $this->rules->assertNoStudentConflict($student, $class); }
                catch (EnrollmentConflict $e) { $reason = $e->getMessage(); }
            }
            $data[] = [
                'id' => $class->id, 'term_id' => $class->term_id, 'class_name' => $class->name,
                'course_name' => $class->course->title, 'teacher_name' => trim($class->teacher->user->first_name.' '.$class->teacher->user->last_name),
                'term_year' => $class->term->year, 'term_season' => $class->term->season,
                'tuition_fee' => $class->tuition_fee, 'capacity' => $class->capacity,
                'registered_students_count' => $class->registrations_count,
                'available_capacity' => $class->capacity - $class->registrations_count,
                'start_date' => $class->start_date, 'end_date' => $class->end_date,
                'startRegistration_Date' => $class->startRegistration_Date, 'endRegistration_Date' => $class->endRegistration_Date,
                'schedules' => $class->schedules->map->only(['id', 'day_of_week', 'start_time', 'end_time'])->values(),
                'is_registered' => (bool) $registered, 'can_register' => $student !== null && $reason === null,
                'registration_block_reason' => $reason,
            ];
        }
        return ['status' => true, 'message' => 'Active courses retrieved successfully', 'data' => $data,
            'registration_block_code' => $blockedCode, 'registration_block_reason' => $blockedReason];
    }
}

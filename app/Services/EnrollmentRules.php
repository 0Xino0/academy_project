<?php

namespace App\Services;

use App\Exceptions\EnrollmentConflict;
use App\Models\ClassModel;
use App\Models\Debt;
use App\Models\Registration;
use App\Models\Student;
use App\Support\WeeklySchedule;

class EnrollmentRules
{
    public function __construct(private StudentScheduleValidator $schedules) {}

    public function assertNoPreviousDebt(Student $student): void
    {
        $registrations = Registration::withTrashed()->where('student_id', $student->id)->get(['id']);
        if (Registration::withTrashed()->whereIn('id', $registrations->modelKeys())->whereHas('class', fn ($query) => $query->whereNull('classes.deleted_at'))->whereDoesntHave('debt')->exists()) {
            throw new EnrollmentConflict('missing_billing_record', 'An earlier enrollment has no billing record. Contact the academy.');
        }
        if (Debt::whereIn('registration_id', $registrations->modelKeys())->where('remaining', '>', 0)->exists()) {
            throw new EnrollmentConflict('outstanding_debt', 'Settle all previous debt before enrolling in another class.');
        }
    }

    public function assertClassAvailable(ClassModel $class): void
    {
        $today = now()->toDateString();
        if (! $class->term?->is_active || $class->term->trashed() || ! $class->course || $class->course->trashed()
            || ! $class->teacher?->user || $class->teacher->user->trashed()) {
            throw new EnrollmentConflict('class_unavailable', 'This class or its teacher is not available for enrollment.');
        }
        if (! $class->startRegistration_Date || ! $class->endRegistration_Date
            || $today < $class->startRegistration_Date || $today > $class->endRegistration_Date) {
            throw new EnrollmentConflict('registration_closed', 'The registration window is not open.');
        }
        if ($class->start_date <= $today) {
            throw new EnrollmentConflict('class_started', 'This class has already started.');
        }
        if ($class->registrations()->count() >= $class->capacity) {
            throw new EnrollmentConflict('class_full', 'This class is full.');
        }
        if ($class->schedules->isEmpty()) {
            throw new EnrollmentConflict('schedule_unavailable', 'The class schedule is not yet available.');
        }
        foreach ($class->schedules as $schedule) {
            $this->validTimes($schedule);
        }
    }

    public function assertNoStudentConflict(Student $student, ClassModel $class, bool $lockPeers = false): void
    {
        $this->schedules->assertAvailable($class, [$student->id], $lockPeers);
    }

    private function validTimes($schedule): array
    {
        $slot = WeeklySchedule::slot($schedule);
        if ($slot === null) {
            throw new EnrollmentConflict('schedule_unavailable', 'A class schedule needs correction by the academy.');
        }
        return [$slot['start_time'], $slot['end_time']];
    }
}

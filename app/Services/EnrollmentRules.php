<?php

namespace App\Services;

use App\Exceptions\EnrollmentConflict;
use App\Models\ClassModel;
use App\Models\Debt;
use App\Models\Registration;
use App\Models\Student;
use App\Support\ScheduleTime;
use Carbon\CarbonImmutable;

class EnrollmentRules
{
    public function assertNoPreviousDebt(Student $student): void
    {
        $registrations = Registration::withTrashed()->where('student_id', $student->id)->get(['id']);
        if (Registration::withTrashed()->whereIn('id', $registrations->modelKeys())->whereDoesntHave('debt')->exists()) {
            throw new EnrollmentConflict('missing_billing_record', 'An earlier enrollment has no billing record. Contact the academy.');
        }
        if (Debt::whereIn('registration_id', $registrations->modelKeys())->where('remaining', '>', 0)->exists()) {
            throw new EnrollmentConflict('outstanding_debt', 'Settle all previous debt before enrolling in another class.');
        }
    }

    public function assertClassAvailable(ClassModel $class): void
    {
        $today = now()->toDateString();
        if (! $class->term?->is_active || ! $class->teacher?->user) {
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

    public function assertNoStudentConflict(Student $student, ClassModel $class): void
    {
        $existing = Registration::where('student_id', $student->id)->where('class_id', '!=', $class->id)
            ->with('class.schedules')->get();
        foreach ($existing as $registration) {
            $other = $registration->class;
            $start = max($class->start_date, $other->start_date);
            $end = min($class->end_date, $other->end_date);
            if ($start > $end) continue;
            if ($other->schedules->isEmpty()) {
                throw new EnrollmentConflict('schedule_unavailable', 'An earlier class has no schedule. Contact the academy before enrolling in an overlapping date range.');
            }
            foreach ($class->schedules as $newSlot) {
                [$newStart, $newEnd] = $this->validTimes($newSlot);
                foreach ($other->schedules as $oldSlot) {
                    [$oldStart, $oldEnd] = $this->validTimes($oldSlot);
                    if ($newSlot->day_of_week !== $oldSlot->day_of_week) continue;
                    $weekday = array_search($newSlot->day_of_week, ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'], true);
                    $first = CarbonImmutable::parse($start);
                    if ($first->addDays(($weekday - $first->dayOfWeek + 7) % 7)->toDateString() > $end) continue;
                    if ($newStart < $oldEnd && $newEnd > $oldStart) {
                        throw new EnrollmentConflict('schedule_conflict', 'This class overlaps another active enrollment in your schedule.');
                    }
                }
            }
        }
    }

    private function validTimes($schedule): array
    {
        $start = ScheduleTime::normalize($schedule->start_time);
        $end = ScheduleTime::normalize($schedule->end_time);
        if ($start === null || $end === null || $start >= $end
            || ! in_array($schedule->day_of_week, ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'], true)) {
            throw new EnrollmentConflict('schedule_unavailable', 'A class schedule needs correction by the academy.');
        }
        return [$start, $end];
    }
}

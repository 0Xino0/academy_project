<?php

namespace App\Services;

use App\Exceptions\EnrollmentConflict;
use App\Models\ClassModel;
use App\Models\Registration;
use App\Models\Student;
use App\Support\WeeklySchedule;
use Illuminate\Validation\ValidationException;

class StudentScheduleValidator
{
    // Caller already holds the term lock; enrollment/cancellation in this class cannot change this set.
    public function lockParticipants(ClassModel $class): array
    {
        $ids = $class->registrations()->orderBy('student_id')->pluck('student_id');
        return Student::withTrashed()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get(['id'])->modelKeys();
    }

    public function ensureEditable(ClassModel $class, array $studentIds): void
    {
        try {
            $this->assertAvailable($class, $studentIds, true, true);
        } catch (EnrollmentConflict $error) {
            throw ValidationException::withMessages(['schedule' => $error->getMessage()]);
        }
    }

    public function assertAvailable(ClassModel $class, array $studentIds, bool $lockPeers = false, bool $allowMissingPeerSchedules = false): void
    {
        if ($studentIds === []) return;
        $class->load('schedules');
        if ($class->schedules->isEmpty()) {
            throw new EnrollmentConflict('schedule_unavailable', 'The class needs a timetable for its enrolled students.');
        }
        $slots = $class->schedules->map(fn ($schedule) => $this->slot($schedule));
        $memberships = Registration::whereIn('student_id', $studentIds)->where('class_id', '!=', $class->id)
            ->orderBy('class_id')->orderBy('student_id')->when($lockPeers, fn ($query) => $query->lockForUpdate())
            ->get(['class_id']);
        $others = ClassModel::whereIn('id', $memberships->pluck('class_id')->unique())
            ->where('start_date', '<=', $class->end_date)->where('end_date', '>=', $class->start_date)
            ->with(['schedules' => fn ($query) => $query->orderBy('id')->when($lockPeers, fn ($query) => $query->lockForUpdate())])
            ->orderBy('id')->when($lockPeers, fn ($query) => $query->lockForUpdate())->get();
        foreach ($others as $other) {
            if ($other->schedules->isEmpty()) {
                // Existing classes may be repaired one by one; a missing timetable has no actual lesson to collide with.
                // New enrollment still requires the other timetables to be available.
                if ($allowMissingPeerSchedules) continue;
                throw new EnrollmentConflict('schedule_unavailable', 'Another enrolled class has no timetable. Correct it before changing overlapping class dates or schedules.');
            }
            $start = max($class->start_date, $other->start_date);
            $end = min($class->end_date, $other->end_date);
            foreach ($other->schedules as $schedule) {
                $existing = $this->slot($schedule);
                foreach ($slots as $proposed) {
                    if (WeeklySchedule::overlaps($proposed, $existing) && WeeklySchedule::occursBetween($start, $end, $proposed['day_of_week'])) {
                        throw new EnrollmentConflict('schedule_conflict', 'This timetable overlaps another active enrollment of a student in this class.');
                    }
                }
            }
        }
    }

    private function slot($schedule): array
    {
        $slot = WeeklySchedule::slot($schedule);
        if ($slot === null) throw new EnrollmentConflict('schedule_unavailable', 'An enrolled class has an invalid timetable. Correct it before continuing.');
        return $slot;
    }
}

<?php

namespace App\Services;

use App\Models\ClassModel;
use App\Models\Schedule;
use App\Models\Teacher;
use App\Models\Term;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ScheduleService
{
    public function __construct(private ScheduleConflictValidator $conflicts, private StudentScheduleValidator $students) {}

    public function save(string $termId, string $classId, array $data, ?string $scheduleId = null): Schedule
    {
        return DB::transaction(function () use ($termId, $classId, $data, $scheduleId) {
            [$class, $studentIds] = $this->lockClass($termId, $classId);
            $schedule = $scheduleId === null ? new Schedule(['class_id' => $class->id])
                : $class->schedules()->lockForUpdate()->findOrFail($scheduleId);
            $this->conflicts->ensureAvailable($class, $data, $schedule->id);
            $schedule->fill($data);
            if (! $schedule->save()) {
                throw new RuntimeException('The schedule could not be saved.');
            }
            $this->students->ensureEditable($class, $studentIds);

            return $schedule->load('class');
        }, 3);
    }

    public function delete(string $termId, string $classId, string $scheduleId): void
    {
        DB::transaction(function () use ($termId, $classId, $scheduleId) {
            [$class] = $this->lockClass($termId, $classId);
            $schedule = $class->schedules()->lockForUpdate()->findOrFail($scheduleId);
            if (! $schedule->delete()) {
                throw new RuntimeException('The schedule could not be deleted.');
            }
        }, 3);
    }

    private function lockClass(string $termId, string $classId): array
    {
        Term::whereKey($termId)->lockForUpdate()->firstOrFail();
        $class = ClassModel::where('term_id', $termId)->findOrFail($classId);
        $studentIds = $this->students->lockParticipants($class);
        // Students before teacher/class: shared participants serialize cross-term timetable changes.
        Teacher::withTrashed()->whereKey($class->teacher_id)->lockForUpdate()->firstOrFail();

        return [ClassModel::where('term_id', $termId)->lockForUpdate()->findOrFail($classId), $studentIds];
    }

}

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
    public function __construct(private ScheduleConflictValidator $conflicts) {}

    public function save(string $termId, string $classId, array $data, ?string $scheduleId = null): Schedule
    {
        return DB::transaction(function () use ($termId, $classId, $data, $scheduleId) {
            $class = $this->lockClass($termId, $classId);
            $schedule = $scheduleId === null ? new Schedule(['class_id' => $class->id])
                : $class->schedules()->lockForUpdate()->findOrFail($scheduleId);
            $this->conflicts->ensureAvailable($class, $data, $schedule->id);
            $schedule->fill($data);
            if (! $schedule->save()) {
                throw new RuntimeException('The schedule could not be saved.');
            }

            return $schedule->load('class');
        });
    }

    public function delete(string $termId, string $classId, string $scheduleId): void
    {
        DB::transaction(function () use ($termId, $classId, $scheduleId) {
            $class = $this->lockClass($termId, $classId);
            $schedule = $class->schedules()->lockForUpdate()->findOrFail($scheduleId);
            if (! $schedule->delete()) {
                throw new RuntimeException('The schedule could not be deleted.');
            }
        });
    }

    private function lockClass(string $termId, string $classId): ClassModel
    {
        Term::whereKey($termId)->lockForUpdate()->firstOrFail();
        $class = ClassModel::where('term_id', $termId)->findOrFail($classId);
        // Same teacher first, then class: concurrent schedule writers share a lock order.
        Teacher::withTrashed()->whereKey($class->teacher_id)->lockForUpdate()->firstOrFail();

        return ClassModel::where('term_id', $termId)->lockForUpdate()->findOrFail($classId);
    }

}

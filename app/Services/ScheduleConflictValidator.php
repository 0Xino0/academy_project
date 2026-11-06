<?php

namespace App\Services;

use App\Models\ClassModel;
use App\Models\Schedule;
use App\Support\ScheduleTime;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class ScheduleConflictValidator
{
    public function ensureAvailable(ClassModel $class, array $data, ?int $ignoreId): void
    {
        $candidates = Schedule::with(['class' => fn ($query) => $query->select('id', 'start_date', 'end_date')->lockForUpdate()])
            ->whereRaw('LOWER(TRIM(day_of_week)) = ?', [$data['day_of_week']])
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->whereHas('class', function ($query) use ($class) {
                $query->where('start_date', '<=', $class->end_date)
                    ->where('end_date', '>=', $class->start_date)
                    ->where(function ($query) use ($class) {
                        $query->where('id', $class->id)->orWhere('teacher_id', $class->teacher_id);
                    });
            })
            ->lockForUpdate()
            ->get();

        foreach ($candidates as $existing) {
            $sharedStart = CarbonImmutable::parse(max($class->start_date, $existing->class->start_date));
            $sharedEnd = min($class->end_date, $existing->class->end_date);
            $weekday = array_search($data['day_of_week'], [
                'sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday',
            ], true);
            $firstLesson = $sharedStart->addDays(($weekday - $sharedStart->dayOfWeek + 7) % 7);
            if ($firstLesson->toDateString() > $sharedEnd) {
                continue;
            }
            $start = ScheduleTime::normalize($existing->start_time);
            $end = ScheduleTime::normalize($existing->end_time);
            if ($start === null || $end === null || $start >= $end) {
                throw ValidationException::withMessages([
                    'schedule' => 'An existing schedule has invalid times. Correct it before adding an overlapping schedule.',
                ]);
            }
            // Half-open intervals allow a lesson to start exactly when the previous one ends.
            if ($start < $data['end_time'] && $end > $data['start_time']) {
                throw ValidationException::withMessages([
                    'schedule' => 'The time overlaps another schedule for this class or teacher.',
                ]);
            }
        }
    }
}

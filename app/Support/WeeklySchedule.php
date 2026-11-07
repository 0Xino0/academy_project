<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class WeeklySchedule
{
    private const DAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

    public static function slot($schedule): ?array
    {
        $day = strtolower(trim($schedule->day_of_week));
        $start = ScheduleTime::normalize($schedule->start_time);
        $end = ScheduleTime::normalize($schedule->end_time);
        if (! in_array($day, self::DAYS, true) || $start === null || $end === null || $start >= $end) return null;
        return ['day_of_week' => $day, 'start_time' => $start, 'end_time' => $end];
    }

    public static function occursBetween(string $start, string $end, string $day): bool
    {
        $weekday = array_search($day, self::DAYS, true);
        if ($start > $end || $weekday === false) return false;
        $first = CarbonImmutable::parse($start);
        return $first->addDays(($weekday - $first->dayOfWeek + 7) % 7)->toDateString() <= $end;
    }

    public static function overlaps(array $first, array $second): bool
    {
        return $first['day_of_week'] === $second['day_of_week']
            && $first['start_time'] < $second['end_time'] && $first['end_time'] > $second['start_time'];
    }
}

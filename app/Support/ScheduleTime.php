<?php

namespace App\Support;

final class ScheduleTime
{
    public static function normalize(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);
        if (preg_match('/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/', $value, $parts)) {
            return $parts[1].':'.$parts[2].':'.($parts[3] ?? '00');
        }

        if (preg_match('/^(0?[1-9]|1[0-2]):([0-5]\d) (AM|PM)$/i', $value, $parts)) {
            $hour = ((int) $parts[1] % 12) + (strtoupper($parts[3]) === 'PM' ? 12 : 0);

            return sprintf('%02d:%s:00', $hour, $parts[2]);
        }

        return null;
    }
}

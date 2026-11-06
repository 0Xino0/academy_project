<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function cents(mixed $value): int
    {
        if (! is_scalar($value) || ! preg_match('/^(\d{1,8})(?:\.(\d{1,2}))?$/', (string) $value, $parts)) {
            throw new InvalidArgumentException('Invalid monetary amount.');
        }

        return (int) $parts[1] * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
    }

    public static function decimal(int $cents): string
    {
        if ($cents < 0) {
            throw new InvalidArgumentException('Negative monetary amounts are not supported.');
        }

        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}

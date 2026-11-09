<?php

namespace App\Services\PowerSchool;

use Carbon\CarbonImmutable;

/**
 * Lenient coercion of PowerSchool's loosely typed JSON values.
 */
final class Value
{
    private function __construct() {}

    public static function str(mixed $value): ?string
    {
        if (is_bool($value) || is_array($value) || $value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public static function int(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    public static function decimal(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    public static function bool(mixed $value): ?bool
    {
        if ($value === null || is_array($value)) {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Date-only fields are taken at face value: "2026-11-03T00:00:00.000Z"
     * means Nov 3 at the school, not Nov 2 after shifting UTC to Chicago.
     * Epoch milliseconds are read in the district timezone.
     */
    public static function date(mixed $value): ?string
    {
        if (is_string($value) && preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $m) === 1) {
            return $m[1];
        }

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return CarbonImmutable::createFromTimestampMs((int) $value)
                ->setTimezone((string) config('powerschool.timezone'))
                ->toDateString();
        }

        return null;
    }
}

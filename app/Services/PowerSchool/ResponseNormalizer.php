<?php

namespace App\Services\PowerSchool;

use App\Services\PowerSchool\Exceptions\UnexpectedPayload;

/**
 * Turns the XML-to-JSON output of the SOAP service into plain PHP arrays:
 * unwraps the method envelope, maps {"@nil": true} to null, and makes sure
 * keys that are lists are always lists (a single item arrives as an object).
 */
final class ResponseNormalizer
{
    /**
     * Keys whose value is a list in the schema, wherever they appear.
     */
    public const LIST_KEYS = [
        'studentIDs',
        'messageVOs',
        'studentDataVOs',
        'assignmentCategories',
        'assignmentScores',
        'assignments',
        'attendance',
        'attendanceCodes',
        'bulletins',
        'finalGrades',
        'notInSessionDays',
        'periods',
        'reportingTerms',
        'schools',
        'sections',
        'teachers',
        'terms',
    ];

    /**
     * @param  array<mixed>|null  $decoded
     * @return array<string, mixed>
     */
    public static function unwrap(string $method, ?array $decoded): array
    {
        $return = $decoded[$method.'Response']['return'] ?? null;

        if (! is_array($return)) {
            throw new UnexpectedPayload("Response to {$method} is missing the {$method}Response.return wrapper.");
        }

        return self::normalize($return);
    }

    public static function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (self::isNil($value)) {
            return null;
        }

        $out = [];

        foreach ($value as $key => $child) {
            $child = self::normalize($child);

            if (is_string($key) && in_array($key, self::LIST_KEYS, true)) {
                $child = self::asList($child);
            }

            $out[$key] = $child;
        }

        return $out;
    }

    /**
     * @param  array<mixed>  $value
     */
    private static function isNil(array $value): bool
    {
        return array_key_exists('@nil', $value)
            && filter_var($value['@nil'], FILTER_VALIDATE_BOOL);
    }

    /**
     * @return list<mixed>
     */
    private static function asList(mixed $value): array
    {
        return match (true) {
            $value === null => [],
            is_array($value) && array_is_list($value) => $value,
            default => [$value],
        };
    }
}

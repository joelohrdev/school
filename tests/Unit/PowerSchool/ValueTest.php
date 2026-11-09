<?php

use App\Services\PowerSchool\Value;

it('coerces strings, blanking empties and non-scalars', function (mixed $in, ?string $out) {
    expect(Value::str($in))->toBe($out);
})->with([
    ['  Math 7 ', 'Math 7'], ['', null], [null, null], [12, '12'], [true, null], [['x'], null],
]);

it('coerces booleans from the shapes PowerSchool uses', function (mixed $in, ?bool $out) {
    expect(Value::bool($in))->toBe($out);
})->with([
    [true, true], [false, false], [1, true], [0, false], ['true', true], ['false', false], ['1', true], [null, null], ['maybe', null],
]);

it('coerces numbers', function () {
    expect(Value::int('42'))->toBe(42)
        ->and(Value::int('x'))->toBeNull()
        ->and(Value::decimal('84.5'))->toBe(84.5)
        ->and(Value::decimal(null))->toBeNull();
});

it('takes ISO dates at face value without shifting timezones', function () {
    expect(Value::date('2026-11-03T00:00:00.000Z'))->toBe('2026-11-03')
        ->and(Value::date('2026-11-03'))->toBe('2026-11-03')
        ->and(Value::date('soon'))->toBeNull()
        ->and(Value::date(null))->toBeNull();
});

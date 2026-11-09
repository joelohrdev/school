<?php

use App\Services\PowerSchool\Exceptions\UnexpectedPayload;
use App\Services\PowerSchool\ResponseNormalizer;

it('unwraps both the method response and return levels', function () {
    $out = ResponseNormalizer::unwrap('getThing', [
        'getThingResponse' => ['return' => ['answer' => 42]],
    ]);

    expect($out)->toBe(['answer' => 42]);
});

it('throws when the wrapper is missing', function (?array $decoded) {
    ResponseNormalizer::unwrap('getThing', $decoded);
})->with([
    'null' => [null],
    'wrong method' => [['otherResponse' => ['return' => []]]],
    'no return' => [['getThingResponse' => ['value' => []]]],
    'scalar return' => [['getThingResponse' => ['return' => 'oops']]],
])->throws(UnexpectedPayload::class);

it('turns nil markers into null at any depth', function () {
    $out = ResponseNormalizer::normalize([
        'a' => ['@nil' => true],
        'b' => ['c' => ['d' => ['@nil' => 'true']]],
        'e' => ['@nil' => false, 'kept' => 1],
    ]);

    expect($out['a'])->toBeNull()
        ->and($out['b']['c']['d'])->toBeNull()
        ->and($out['e'])->toBe(['@nil' => false, 'kept' => 1]);
});

it('wraps a single-object list key into a one-item list', function () {
    $out = ResponseNormalizer::normalize([
        'assignmentScores' => ['id' => 1, 'missing' => true],
    ]);

    expect($out['assignmentScores'])->toBe([['id' => 1, 'missing' => true]]);
});

it('leaves real lists alone', function () {
    $out = ResponseNormalizer::normalize(['sections' => [['id' => 1], ['id' => 2]]]);

    expect($out['sections'])->toBe([['id' => 1], ['id' => 2]]);
});

it('turns a nil or scalar list key into a list', function () {
    $out = ResponseNormalizer::normalize([
        'teachers' => ['@nil' => true],
        'studentIDs' => 1001,
    ]);

    expect($out['teachers'])->toBe([])
        ->and($out['studentIDs'])->toBe([1001]);
});

it('does not touch keys that are not lists', function () {
    $out = ResponseNormalizer::normalize(['student' => ['id' => 1]]);

    expect($out['student'])->toBe(['id' => 1]);
});

it('normalizes list keys nested inside list items', function () {
    $out = ResponseNormalizer::normalize([
        'studentDataVOs' => ['student' => ['id' => 1], 'sections' => ['id' => 9]],
    ]);

    expect($out['studentDataVOs'])->toHaveCount(1)
        ->and($out['studentDataVOs'][0]['sections'])->toBe([['id' => 9]]);
});

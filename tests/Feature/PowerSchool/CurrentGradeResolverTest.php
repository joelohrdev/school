<?php

use App\Models\FinalGrade;
use App\Models\ReportingTerm;
use App\Services\PowerSchool\CurrentGradeResolver;
use Carbon\CarbonImmutable;

function grade(?float $percent, ?string $start, ?string $end, ?string $asOf = null): FinalGrade
{
    $grade = new FinalGrade(['percent' => $percent, 'letter_grade' => null, 'as_of' => $asOf]);
    $grade->setRelation('reportingTerm', new ReportingTerm(['starts_on' => $start, 'ends_on' => $end]));

    return $grade;
}

it('prefers the shortest graded term containing today', function () {
    $quarter = grade(80, '2026-10-20', '2026-12-19');
    $semester = grade(85, '2026-08-20', '2026-12-19');

    $picked = (new CurrentGradeResolver)->pick(collect([$semester, $quarter]), CarbonImmutable::parse('2026-11-09'));

    expect($picked)->toBe($quarter);
});

it('ignores ungraded terms', function () {
    $empty = grade(null, '2026-10-20', '2026-12-19');
    $semester = grade(85, '2026-08-20', '2026-12-19');

    expect((new CurrentGradeResolver)->pick(collect([$empty, $semester]), CarbonImmutable::parse('2026-11-09')))->toBe($semester);
});

it('falls back to the latest stored grade outside any term', function () {
    $q1 = grade(70, '2026-08-20', '2026-10-17', '2026-10-17');
    $q2 = grade(75, '2026-10-20', '2026-12-19', '2026-12-19');

    expect((new CurrentGradeResolver)->pick(collect([$q1, $q2]), CarbonImmutable::parse('2027-01-02')))->toBe($q2);
});

it('returns null with no grades', function () {
    expect((new CurrentGradeResolver)->pick(collect(), CarbonImmutable::parse('2026-11-09')))->toBeNull();
});

<?php

use App\Models\Assignment;
use App\Models\FinalGrade;
use App\Models\ReportingTerm;
use App\Models\SchoolQuarter;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\PowerSchool\SyncService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'powerschool.base_url' => 'https://ps.example.test',
        'powerschool.username' => 'parent@example.test',
        'powerschool.password' => 'secret',
    ]);

    // The fixture's Q2 runs Oct 20 to Dec 19.
    $this->travelTo(CarbonImmutable::parse('2026-11-09 13:00:00', 'UTC'));
    fakePowerSchoolSync();
    app(SyncService::class)->run();

    $this->actingAs(User::factory()->create());
});

/** A Q1 term with a Math grade and one September assignment. */
function addFirstQuarter(): Section
{
    $math = Section::query()->where('course_name', 'Math 7')->firstOrFail();
    $q1 = ReportingTerm::create(['ps_id' => 76, 'abbreviation' => 'Q1', 'title' => 'Quarter 1', 'starts_on' => '2026-08-19', 'ends_on' => '2026-10-19']);
    FinalGrade::create(['student_id' => $math->student_id, 'section_id' => $math->id, 'reporting_term_id' => $q1->id, 'ps_id' => 4999, 'percent' => 91, 'letter_grade' => 'A-']);
    Assignment::create(['section_id' => $math->id, 'ps_id' => 9001, 'name' => 'Integers quiz', 'due_on' => '2026-09-15']);

    return $math;
}

it('opens on the current PowerSchool quarter', function () {
    $student = Student::firstOrFail();

    $this->get(route('students.show', $student))->assertInertia(fn (Assert $page) => $page
        ->where('quarters.selected', 'q2')
        ->where('quarters.options.0.name', 'Q2')
        ->where('quarters.options.0.startsOn', '2026-10-20')
        ->has('quarters.options', 1)
        ->has('assignments', 2));
});

it('shows only a past quarter\'s work and grade', function () {
    $math = addFirstQuarter();

    $this->get(route('students.show', [$math->student_id, 'quarter' => 'q1']))->assertInertia(fn (Assert $page) => $page
        ->where('quarters.selected', 'q1')
        ->has('quarters.options', 2)
        ->has('assignments', 1)
        ->where('assignments.0.name', 'Integers quiz')
        ->where('classes.0.course', 'Math 7')
        ->where('classes.0.percent', 91)
        ->where('classes.0.term', 'Q1')
        ->where('classes.1.percent', null));

    $this->get(route('students.show', [$math->student_id, 'quarter' => 'q2']))->assertInertia(fn (Assert $page) => $page
        ->has('assignments', 2)
        ->where('classes.0.percent', 74.5));

    $this->get(route('sections.show', [$math, 'quarter' => 'q1']))->assertInertia(fn (Assert $page) => $page
        ->where('section.percent', 91)
        ->has('assignments', 1));
});

it('shows the whole year on request', function () {
    $math = addFirstQuarter();

    $this->get(route('students.show', [$math->student_id, 'quarter' => 'year']))->assertInertia(fn (Assert $page) => $page
        ->where('quarters.selected', 'year')
        ->has('assignments', 3)
        ->where('classes.0.percent', 74.5));
});

it('lists PowerSchool\'s quarters read-only in settings', function () {
    $this->get(route('quarters.edit'))->assertInertia(fn (Assert $page) => $page
        ->component('settings/quarters')
        ->where('fromPowerSchool', true)
        ->where('powerSchoolQuarters.0.name', 'Q2'));
});

it('uses entered quarters when PowerSchool sends none', function () {
    ReportingTerm::query()->update(['abbreviation' => 'S1']);
    $student = Student::firstOrFail();

    $this->get(route('students.show', $student))->assertInertia(fn (Assert $page) => $page
        ->has('quarters.options', 0)
        ->where('quarters.selected', 'year'));

    $this->put(route('quarters.update'), ['quarters' => [
        ['name' => 'Fall A', 'starts_on' => '2026-08-19', 'ends_on' => '2026-10-16'],
        ['name' => 'Fall B', 'starts_on' => '2026-10-17', 'ends_on' => '2026-12-23'],
    ]])->assertRedirect(route('quarters.edit'));

    expect(SchoolQuarter::count())->toBe(2);

    $this->get(route('students.show', $student))->assertInertia(fn (Assert $page) => $page
        ->where('quarters.selected', 'fall-b')
        ->has('quarters.options', 2)
        ->has('assignments', 2));

    $this->get(route('quarters.edit'))->assertInertia(fn (Assert $page) => $page
        ->where('fromPowerSchool', false)
        ->where('quarters.1.name', 'Fall B'));
});

it('matches an entered quarter to PowerSchool\'s grade by its dates', function () {
    $math = addFirstQuarter();
    ReportingTerm::query()->update(['abbreviation' => 'T']);
    SchoolQuarter::create(['name' => 'First', 'starts_on' => '2026-08-20', 'ends_on' => '2026-10-16']);

    $this->get(route('sections.show', [$math, 'quarter' => 'first']))->assertInertia(fn (Assert $page) => $page
        ->where('section.percent', 91));
});

it('rejects overlapping or backwards quarters', function (array $quarters, string $field) {
    $this->put(route('quarters.update'), ['quarters' => $quarters])->assertSessionHasErrors($field);

    expect(SchoolQuarter::count())->toBe(0);
})->with([
    'overlap' => [[
        ['name' => 'Q1', 'starts_on' => '2026-08-19', 'ends_on' => '2026-10-20'],
        ['name' => 'Q2', 'starts_on' => '2026-10-17', 'ends_on' => '2026-12-23'],
    ], 'quarters'],
    'backwards' => [[['name' => 'Q1', 'starts_on' => '2026-10-19', 'ends_on' => '2026-08-19']], 'quarters.0.ends_on'],
    'same name' => [[
        ['name' => 'Q1', 'starts_on' => '2026-08-19', 'ends_on' => '2026-09-01'],
        ['name' => 'q1', 'starts_on' => '2026-09-02', 'ends_on' => '2026-10-01'],
    ], 'quarters.1.name'],
]);

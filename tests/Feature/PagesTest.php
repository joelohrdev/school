<?php

use App\Jobs\SyncPowerSchoolJob;
use App\Models\Alert;
use App\Models\Assignment;
use App\Models\AssignmentScore;
use App\Models\FinalGrade;
use App\Models\ReportingTerm;
use App\Models\Section;
use App\Models\Student;
use App\Models\SyncRun;
use App\Models\User;
use App\Services\PowerSchool\SyncService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'powerschool.base_url' => 'https://ps.example.test',
        'powerschool.username' => 'parent@example.test',
        'powerschool.password' => 'secret',
    ]);

    $this->travelTo(CarbonImmutable::parse('2026-11-09 13:00:00', 'UTC'));
    fakePowerSchoolSync();
    app(SyncService::class)->run();

    $this->actingAs(User::factory()->create());
});

it('keeps every page behind login', function (string $uri) {
    auth()->logout();

    $this->get($uri)->assertRedirect(route('login'));
})->with(['/dashboard', '/students/1', '/classes/1', '/alerts', '/sync']);

it('shows one card per kid with missing work and current grades', function () {
    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->has('students', 2)
        ->where('students.0.name', 'Avery')
        ->where('students.0.missingCount', 1)
        ->where('students.0.lateCount', 1)
        ->where('students.0.missing.0.name', 'Fractions worksheet')
        ->where('students.0.missing.0.daysOverdue', 6)
        ->where('students.0.classes.0.course', 'Math 7')
        ->where('students.0.classes.0.percent', 74.5)
        ->where('students.0.classes.0.letter', 'C')
        ->has('students.0.classes.0.trend', 1)
        ->where('lastSync.status', SyncRun::STATUS_SUCCEEDED)
        ->where('today', '2026-11-09')
        ->where('kids.1.name', 'Blake')
        ->where('unreadAlerts', 0));
});

it('lists work due this week that is not graded, missing or exempt', function () {
    $math = Section::where('ps_id', 401)->sole();
    $due = fn (string $name, string $on) => Assignment::create(['section_id' => $math->id, 'ps_id' => crc32($name), 'name' => $name, 'due_on' => $on]);

    $due('Chapter 4 review', '2026-11-11');
    $due('Due today', '2026-11-09');
    $due('Too far out', '2026-11-20');
    $due('Already past', '2026-11-08');
    $graded = $due('Already graded', '2026-11-10');
    AssignmentScore::create(['student_id' => $math->student_id, 'assignment_id' => $graded->id, 'ps_id' => 1, 'score' => '9']);

    $this->get(route('dashboard'))->assertInertia(fn (Assert $page) => $page
        ->has('students.0.upcoming', 2)
        ->where('students.0.upcoming.0.name', 'Due today')
        ->where('students.0.upcoming.0.daysUntil', 0)
        ->where('students.0.upcoming.1.name', 'Chapter 4 review')
        ->where('students.0.upcoming.1.daysUntil', 2)
        ->has('students.1.upcoming', 0));
});

it('shows a kid with classes, assignments and attendance', function () {
    $avery = Student::where('ps_id', 1001)->sole();

    $this->get(route('students.show', $avery))->assertInertia(fn (Assert $page) => $page
        ->component('students/show')
        ->where('student.fullName', 'Avery Example')
        ->has('classes', 2)
        ->has('assignments', 2)
        ->where('assignments.0.name', 'Cells unit test')
        ->where('assignments.0.late', true)
        ->where('attendance.total', 2)
        ->where('attendance.byCode.0.count', 1));
});

it('shows a class with its history and assignments', function () {
    $math = Section::where('ps_id', 401)->sole();

    $this->get(route('sections.show', $math))->assertInertia(fn (Assert $page) => $page
        ->component('sections/show')
        ->where('section.course', 'Math 7')
        ->where('section.teacherEmail', 'pat.teacher@example.test')
        ->has('section.history', 1)
        ->has('assignments', 1)
        ->where('assignments.0.category', 'Homework')
        ->where('student.name', 'Avery'));
});

it('shows every term on the report card, earliest first', function () {
    $math = Section::where('ps_id', 401)->sole();
    $q1 = ReportingTerm::create(['ps_id' => 76, 'abbreviation' => 'Q1', 'title' => 'Quarter 1', 'starts_on' => '2026-08-20', 'ends_on' => '2026-10-17']);
    FinalGrade::create(['student_id' => $math->student_id, 'section_id' => $math->id, 'reporting_term_id' => $q1->id, 'ps_id' => 4999, 'percent' => 88, 'letter_grade' => 'B+', 'comment' => 'Strong start']);

    $this->get(route('sections.show', $math))->assertInertia(fn (Assert $page) => $page
        ->has('reportCard', 2)
        ->where('reportCard.0.term', 'Q1')
        ->where('reportCard.0.letter', 'B+')
        ->where('reportCard.0.comment', 'Strong start')
        ->where('reportCard.0.current', false)
        ->where('reportCard.1.term', 'Q2')
        ->where('reportCard.1.percent', 74.5)
        ->where('reportCard.1.current', true));
});

it('lists alerts, filters unread, and marks them read', function () {
    $student = Student::first();
    $one = Alert::create(['student_id' => $student->id, 'type' => Alert::TYPE_MISSING, 'payload' => ['assignment_name' => 'A']]);
    Alert::create(['student_id' => $student->id, 'type' => Alert::TYPE_LATE, 'payload' => ['assignment_name' => 'B'], 'read_at' => now()]);

    $this->get(route('alerts.index'))->assertInertia(fn (Assert $page) => $page->component('alerts/index')->has('alerts.data', 2));
    $this->get(route('alerts.index', ['filter' => 'unread']))->assertInertia(fn (Assert $page) => $page->has('alerts.data', 1)->where('unreadAlerts', 1));

    $this->patch(route('alerts.read', $one))->assertRedirect();
    expect($one->fresh()->read_at)->not->toBeNull();

    Alert::create(['student_id' => $student->id, 'type' => Alert::TYPE_MISSING, 'payload' => []]);
    $this->post(route('alerts.read-all'))->assertRedirect();
    expect(Alert::unread()->count())->toBe(0);
});

it('shows sync history and queues one sync at a time', function () {
    Queue::fake();

    $this->get(route('sync.index'))->assertInertia(fn (Assert $page) => $page
        ->component('sync/index')
        ->has('runs', 1)
        ->where('runs.0.status', 'succeeded')
        ->where('busy', false));

    $this->post(route('sync.store'))->assertRedirect();
    $this->post(route('sync.store'))->assertRedirect();

    Queue::assertPushed(SyncPowerSchoolJob::class, 1);

    $this->get(route('sync.index'))->assertInertia(fn (Assert $page) => $page->where('busy', true));
});

it('runs a manual sync from the queued job and clears the busy flag', function () {
    Queue::fake();
    SyncPowerSchoolJob::queueOnce();
    expect(SyncPowerSchoolJob::isQueued())->toBeTrue();

    (new SyncPowerSchoolJob)->handle(app(SyncService::class));

    expect(SyncRun::latest('id')->first()->trigger)->toBe(SyncRun::TRIGGER_MANUAL)
        ->and(SyncPowerSchoolJob::isQueued())->toBeFalse();
});

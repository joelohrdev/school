<?php

use App\Models\Alert;
use App\Models\Assignment;
use App\Models\AssignmentScore;
use App\Models\RawPayload;
use App\Models\Section;
use App\Models\Student;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function makeStudent(int $psId = 1001): Student
{
    return Student::create(['ps_id' => $psId, 'first_name' => 'Avery', 'last_name' => 'Example']);
}

function makeSection(Student $student, int $psId = 401): Section
{
    return $student->sections()->create(['ps_id' => $psId, 'course_name' => 'Math 7']);
}

it('lets siblings share a PowerSchool section id but not duplicate it per student', function () {
    $avery = makeStudent(1001);
    $blake = makeStudent(1002);

    makeSection($avery, 401);
    makeSection($blake, 401);

    expect(Section::count())->toBe(2);

    makeSection($avery, 401);
})->throws(UniqueConstraintViolationException::class);

it('keeps one score per student and assignment', function () {
    $student = makeStudent();
    $assignment = makeSection($student)->assignments()->create(['ps_id' => 7001, 'name' => 'Worksheet']);

    AssignmentScore::create(['student_id' => $student->id, 'assignment_id' => $assignment->id, 'ps_id' => 9101]);
    AssignmentScore::create(['student_id' => $student->id, 'assignment_id' => $assignment->id, 'ps_id' => 9102]);
})->throws(UniqueConstraintViolationException::class);

it('cascades a student delete to everything that hangs off them', function () {
    $student = makeStudent();
    $assignment = makeSection($student)->assignments()->create(['ps_id' => 7001, 'name' => 'Worksheet']);
    AssignmentScore::create(['student_id' => $student->id, 'assignment_id' => $assignment->id, 'ps_id' => 9101]);

    $student->delete();

    expect(Section::count())->toBe(0)
        ->and(Assignment::count())->toBe(0)
        ->and(AssignmentScore::count())->toBe(0);
});

it('scopes missing work to non-exempt scores', function () {
    $student = makeStudent();
    $section = makeSection($student);

    foreach ([[7001, true, false], [7002, true, true], [7003, false, false]] as [$id, $missing, $exempt]) {
        $assignment = $section->assignments()->create(['ps_id' => $id, 'name' => "A{$id}"]);
        AssignmentScore::create([
            'student_id' => $student->id, 'assignment_id' => $assignment->id, 'ps_id' => $id + 2000,
            'missing' => $missing, 'exempt' => $exempt,
        ]);
    }

    expect(AssignmentScore::missing()->pluck('ps_id')->all())->toBe([9001]);
});

it('stores raw payloads encrypted at rest', function () {
    $student = makeStudent();
    RawPayload::create(['student_id' => $student->id, 'payload' => ['student' => ['firstName' => 'Avery']]]);

    $stored = DB::table('raw_payloads')->value('payload');

    expect($stored)->not->toContain('Avery')
        ->and(RawPayload::first()->payload['student']['firstName'])->toBe('Avery');
});

it('prunes raw payloads older than 14 days', function () {
    $student = makeStudent();

    $this->travelTo(now()->subDays(15));
    RawPayload::create(['student_id' => $student->id, 'payload' => ['old' => true]]);
    $this->travelBack();
    RawPayload::create(['student_id' => $student->id, 'payload' => ['new' => true]]);

    $this->artisan('model:prune', ['--model' => [RawPayload::class]])->assertSuccessful();

    expect(RawPayload::count())->toBe(1)
        ->and(RawPayload::first()->payload)->toBe(['new' => true]);
});

it('marks alerts as read once', function () {
    $alert = Alert::create(['student_id' => makeStudent()->id, 'type' => Alert::TYPE_MISSING, 'payload' => ['assignment' => 'Worksheet']]);

    expect(Alert::unread()->count())->toBe(1);

    $alert->markAsRead();
    $readAt = $alert->read_at;
    $this->travel(1)->hours();
    $alert->markAsRead();

    expect(Alert::unread()->count())->toBe(0)
        ->and($alert->fresh()->read_at->equalTo($readAt))->toBeTrue();
});

<?php

use App\Mail\DailyDigest;
use App\Mail\MissingWorkAlert;
use App\Models\Alert;
use App\Models\Assignment;
use App\Models\AssignmentScore;
use App\Models\ReportingTerm;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentSnapshot;
use App\Services\Digest\DigestBuilder;
use App\Services\Digest\DigestSender;
use App\Services\PowerSchool\SyncService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Http::preventStrayRequests();

    config([
        'powerschool.base_url' => 'https://ps.example.test',
        'powerschool.username' => 'parent@example.test',
        'powerschool.password' => 'secret',
        'powerschool.digest_to' => 'parent@example.test',
    ]);

    $this->travelTo(CarbonImmutable::parse('2026-11-09 12:30:00', 'UTC'));
});

/** Baseline sync, then a sync where Science 7's test goes missing and Math drops 5 points. */
function syncWithNews(): void
{
    fakePowerSchoolSync();
    app(SyncService::class)->run();

    $data = powerschoolFixtureData('student_data');
    $kid = &$data['getStudentDataResponse']['return']['studentDataVOs'][0];
    $kid['assignmentScores'][1]['missing'] = true;
    $kid['finalGrades'][0]['percent'] = 69.5;
    unset($kid);

    test()->travel(1)->days();
    fakePowerSchoolSync($data);
    app(SyncService::class)->run();
}

it('sends nothing without a recipient', function () {
    config(['powerschool.digest_to' => null]);
    syncWithNews();

    expect(app(DigestSender::class)->send())->toBe(DigestSender::NO_RECIPIENT);
    Mail::assertNothingSent();
});

it('sends nothing on a no-change day', function () {
    fakePowerSchoolSync();
    app(SyncService::class)->run();

    expect(app(DigestSender::class)->send())->toBe(DigestSender::NO_CHANGES);
    Mail::assertNothingSent();
});

it('emails the news once and marks it sent', function () {
    syncWithNews();

    expect(app(DigestSender::class)->send())->toBe(DigestSender::SENT);

    Mail::assertSent(DailyDigest::class, function (DailyDigest $mail) {
        return $mail->hasTo('parent@example.test')
            && $mail->subjectLine() === 'School update: 1 newly missing, 1 grade drop';
    });

    expect(Alert::unnotified()->count())->toBe(0)
        ->and(app(DigestSender::class)->send())->toBe(DigestSender::NO_CHANGES);

    Mail::assertSentCount(1);
});

it('lists newly missing, still missing with the oldest, and grade drops', function () {
    syncWithNews();
    app(DigestSender::class)->send();

    Mail::assertSent(DailyDigest::class, function (DailyDigest $mail) {
        $html = $mail->render();

        return str_contains($html, 'Avery')
            && str_contains($html, 'Cells unit test (Science 7), due Thu Nov 5')
            && str_contains($html, 'Still missing this quarter:</strong> 2 assignments')
            && str_contains($html, 'Oldest is Fractions worksheet (Math 7), due Tue Nov 3, 7 days overdue')
            && str_contains($html, 'Math 7: 74.5% → 69.5%')
            // Every kid gets trends, even one with no news.
            && str_contains($html, 'Blake');
    });
});

it('sends the digest from the command only with --digest', function () {
    fakePowerSchoolSync();
    app(SyncService::class)->run();
    Alert::create(['student_id' => Student::firstOrFail()->id, 'type' => Alert::TYPE_MISSING, 'payload' => [
        'course_name' => 'Math 7', 'assignment_name' => 'Worksheet', 'due_on' => '2026-11-03',
    ]]);

    // Without --digest only the missing-work email goes out.
    $this->artisan('powerschool:sync')->assertSuccessful();
    Mail::assertNotSent(DailyDigest::class);
    Mail::assertSent(MissingWorkAlert::class, 1);

    $this->artisan('powerschool:sync --digest')->expectsOutputToContain('Digest emailed.')->assertSuccessful();
    Mail::assertSent(DailyDigest::class, 1);

    $this->artisan('powerschool:sync --digest')->expectsOutputToContain('No changes; no digest sent.')->assertSuccessful();
});

it('lists new absences and teacher comments', function () {
    fakePowerSchoolSync();
    app(SyncService::class)->run();

    $data = powerschoolFixtureData('student_data');
    $kid = &$data['getStudentDataResponse']['return']['studentDataVOs'][0];
    $kid['attendance'][] = ['id' => 6004, 'attDate' => '2026-11-09T00:00:00.000Z', 'attCodeid' => 22, 'periodid' => 1];
    $kid['assignmentScores'][1]['comment'] = 'Redo question 4 for full credit';
    unset($kid);

    $this->travel(1)->hours();
    fakePowerSchoolSync($data);
    app(SyncService::class)->run();

    expect(app(DigestSender::class)->send())->toBe(DigestSender::SENT);

    Mail::assertSent(DailyDigest::class, function (DailyDigest $mail) {
        $html = $mail->render();

        return $mail->subjectLine() === 'School update: 1 absence or tardy, 1 teacher comment'
            && str_contains($html, 'Attendance')
            && str_contains($html, 'Tardy')
            && str_contains($html, 'Teacher comments')
            && str_contains($html, 'Redo question 4 for full credit');
    });
});

it('shows whether each kid is getting better or worse over the week', function () {
    // A week of history: Avery starts at the fixture's grades with one missing.
    fakePowerSchoolSync();
    app(SyncService::class)->run();

    // A week later: Math falls 5 points, Science's test goes missing.
    $data = powerschoolFixtureData('student_data');
    $kid = &$data['getStudentDataResponse']['return']['studentDataVOs'][0];
    $kid['assignmentScores'][1]['missing'] = true;
    $kid['finalGrades'][0]['percent'] = 69.5;
    unset($kid);

    $this->travel(7)->days();
    fakePowerSchoolSync($data);
    app(SyncService::class)->run();

    expect(app(DigestSender::class)->send())->toBe(DigestSender::SENT);

    Mail::assertSent(DailyDigest::class, function (DailyDigest $mail) {
        $html = $mail->render();

        return str_contains($html, 'Getting worse since Mon Nov 9:</strong> class average 77.8% (down 2.5 points), 2 missing (was 1)')
            && str_contains($html, 'Since Mon Nov 9')
            && str_contains($html, '−5.0')
            && str_contains($html, 'Holding steady since Mon Nov 9:</strong> nothing missing.');
    });
});

it('decides better, worse, mixed and steady', function (float $average, int $missing, string $verdict) {
    expect(DigestBuilder::verdict($average, $missing))->toBe($verdict);
})->with([
    'grades up' => [1.5, 0, 'better'],
    'fewer missing' => [0.2, -2, 'better'],
    'both better' => [2.0, -1, 'better'],
    'grades down' => [-1.0, 0, 'worse'],
    'more missing' => [0.0, 1, 'worse'],
    'one of each' => [3.0, 2, 'mixed'],
    'small wobble' => [0.6, 0, 'steady'],
]);

/** A Q1 term and a Math assignment due in it, so there's an earlier quarter to leave out. */
function addEarlierQuarterWork(): Assignment
{
    ReportingTerm::create(['ps_id' => 76, 'abbreviation' => 'Q1', 'title' => 'Quarter 1', 'starts_on' => '2026-08-19', 'ends_on' => '2026-10-19']);
    $math = Section::query()->where('course_name', 'Math 7')->firstOrFail();

    return Assignment::create(['section_id' => $math->id, 'ps_id' => 9001, 'name' => 'Integers quiz', 'due_on' => '2026-09-15']);
}

it('leaves earlier quarters out of the digest', function () {
    syncWithNews();
    $q1 = addEarlierQuarterWork();
    $avery = Student::query()->where('first_name', 'Avery')->firstOrFail();
    AssignmentScore::create(['student_id' => $avery->id, 'assignment_id' => $q1->id, 'ps_id' => 9901, 'missing' => true]);
    Alert::create(['student_id' => $avery->id, 'type' => Alert::TYPE_MISSING, 'payload' => [
        'course_name' => 'Math 7', 'assignment_name' => 'Integers quiz', 'due_on' => '2026-09-15',
    ]]);

    expect(app(DigestSender::class)->send())->toBe(DigestSender::SENT);

    Mail::assertSent(DailyDigest::class, function (DailyDigest $mail) {
        $html = $mail->render();

        return $mail->subjectLine() === 'School update: 1 newly missing, 1 grade drop'
            && str_contains($html, 'Q2, Tue Oct 20 to Sat Dec 19')
            && str_contains($html, 'Cells unit test')
            && ! str_contains($html, 'Integers quiz')
            && str_contains($html, 'Still missing this quarter:</strong> 2 assignments');
    });

    expect(Alert::unnotified()->count())->toBe(0);
});

it('sends no digest when the only news is from an earlier quarter, and marks it handled', function () {
    fakePowerSchoolSync();
    app(SyncService::class)->run();
    addEarlierQuarterWork();
    Alert::create(['student_id' => Student::firstOrFail()->id, 'type' => Alert::TYPE_SCORE_POSTED, 'payload' => [
        'course_name' => 'Math 7', 'assignment_name' => 'Integers quiz', 'due_on' => '2026-09-15', 'score' => '9',
    ]]);

    expect(app(DigestSender::class)->send())->toBe(DigestSender::NO_CHANGES)
        ->and(Alert::unnotified()->count())->toBe(0);
    Mail::assertNothingSent();
});

it('does not call a new quarter\'s first grade a drop', function () {
    fakePowerSchoolSync();
    app(SyncService::class)->run();

    // Q2 begins tomorrow with a fresh, lower grade.
    ReportingTerm::query()->update(['starts_on' => '2026-11-10']);
    $data = powerschoolFixtureData('student_data');
    $data['getStudentDataResponse']['return']['studentDataVOs'][0]['finalGrades'][0]['percent'] = 60;
    $data['getStudentDataResponse']['return']['studentDataVOs'][0]['reportingTerms'][0]['startDate'] = '2026-11-10T00:00:00.000Z';

    $this->travel(1)->days();
    fakePowerSchoolSync($data);
    app(SyncService::class)->run();

    expect(Alert::where('type', Alert::TYPE_GRADE_DROP)->count())->toBe(0);
});

it('compares missing work within the quarter only', function () {
    syncWithNews();

    expect(StudentSnapshot::query()->whereDate('snapshot_on', '2026-11-10')->where('student_id', Student::query()->where('first_name', 'Avery')->value('id'))->value('quarter_missing_count'))->toBe(2);
});

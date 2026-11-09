<?php

use App\Mail\DailyDigest;
use App\Models\Alert;
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
            && str_contains($html, 'Still missing:</strong> 2 assignments')
            && str_contains($html, 'Oldest is Fractions worksheet (Math 7), due Tue Nov 3, 7 days overdue')
            && str_contains($html, 'Math 7: 74.5% → 69.5%')
            && ! str_contains($html, 'Blake');
    });
});

it('sends the digest from the command only with --digest', function () {
    fakePowerSchoolSync();
    app(SyncService::class)->run();
    Alert::create(['student_id' => 1, 'type' => Alert::TYPE_MISSING, 'payload' => [
        'course_name' => 'Math 7', 'assignment_name' => 'Worksheet', 'due_on' => '2026-11-03',
    ]]);

    $this->artisan('powerschool:sync')->assertSuccessful();
    Mail::assertNothingSent();

    $this->artisan('powerschool:sync --digest')->expectsOutputToContain('Digest emailed.')->assertSuccessful();
    Mail::assertSentCount(1);

    $this->artisan('powerschool:sync --digest')->expectsOutputToContain('No changes; no digest sent.')->assertSuccessful();
});

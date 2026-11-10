<?php

use App\Jobs\SyncPowerSchoolJob;
use App\Mail\DailyDigest;
use App\Mail\MissingWorkAlert;
use App\Models\Alert;
use App\Services\Digest\MissingWorkNotifier;
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

    $this->travelTo(CarbonImmutable::parse('2026-11-09 21:45:00', 'UTC'));

    // Baseline, then Science 7's test goes missing in the next sync's data.
    fakePowerSchoolSync();
    app(SyncService::class)->run();

    $data = powerschoolFixtureData('student_data');
    $data['getStudentDataResponse']['return']['studentDataVOs'][0]['assignmentScores'][1]['missing'] = true;
    $this->travel(1)->hours();
    fakePowerSchoolSync($data);
});

it('emails newly missing work right after the afternoon sync, once', function () {
    $this->artisan('powerschool:sync --trigger=schedule')
        ->expectsOutputToContain('Missing-work email sent.')
        ->assertSuccessful();

    Mail::assertSent(MissingWorkAlert::class, function (MissingWorkAlert $mail) {
        $html = $mail->render();

        return $mail->hasTo('parent@example.test')
            && $mail->subjectLine() === 'Missing work: Avery has 1 new missing assignment'
            && str_contains($html, 'Cells unit test')
            && str_contains($html, 'due Thu Nov 5')
            && str_contains($html, 'Avery has 2 missing assignments in Q2');
    });
    Mail::assertNotSent(DailyDigest::class);

    $this->artisan('powerschool:sync')->assertSuccessful();
    Mail::assertSent(MissingWorkAlert::class, 1);

    // The next morning's digest still lists it.
    $this->artisan('powerschool:sync --digest')->expectsOutputToContain('Digest emailed.');
    Mail::assertSent(DailyDigest::class, fn (DailyDigest $mail) => str_contains($mail->render(), 'Cells unit test'));
});

it('sends only the digest when the morning sync finds the missing work', function () {
    $this->artisan('powerschool:sync --digest')->assertSuccessful();

    Mail::assertSent(DailyDigest::class, 1);
    Mail::assertNotSent(MissingWorkAlert::class);
    expect(Alert::where('type', Alert::TYPE_MISSING)->sole()->alert_emailed_at)->not->toBeNull();
});

it('emails after Sync now too', function () {
    app()->call([new SyncPowerSchoolJob, 'handle']);

    Mail::assertSent(MissingWorkAlert::class, 1);
});

it('sends nothing on a dry run, without a recipient, or when turned off', function () {
    $this->artisan('powerschool:sync --dry-run')->assertSuccessful();
    Mail::assertNothingSent();

    config(['powerschool.missing_alert_emails' => false]);
    $this->artisan('powerschool:sync')->assertSuccessful();
    Mail::assertNothingSent();

    config(['powerschool.missing_alert_emails' => true, 'powerschool.digest_to' => null]);
    expect(app(MissingWorkNotifier::class)->send())->toBe(MissingWorkNotifier::DISABLED);
    Mail::assertNothingSent();
});

it('keeps the sync successful and retries next time when mail fails', function () {
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Resend is down'));

    expect(app(SyncService::class)->run()->status)->toBe('succeeded')
        ->and(app(MissingWorkNotifier::class)->send())->toBe(MissingWorkNotifier::FAILED)
        ->and(Alert::where('type', Alert::TYPE_MISSING)->sole()->alert_emailed_at)->toBeNull();
});

it('names each kid in the subject when several have new missing work', function () {
    $mail = new MissingWorkAlert([
        ['name' => 'Avery', 'newly_missing' => [[], []], 'still_missing_count' => 3],
        ['name' => 'Blake', 'newly_missing' => [[]], 'still_missing_count' => 1],
    ]);

    expect($mail->subjectLine())->toBe('Missing work: Avery 2 new, Blake 1 new');
});

it('does not email missing work from an earlier quarter', function () {
    // Make the newly missing test due before Q2 began.
    $data = powerschoolFixtureData('student_data');
    $data['getStudentDataResponse']['return']['studentDataVOs'][0]['assignmentScores'][1]['missing'] = true;
    $data['getStudentDataResponse']['return']['studentDataVOs'][0]['assignments'][1]['dueDate'] = '2026-10-01T00:00:00.000Z';
    fakePowerSchoolSync($data);

    $this->artisan('powerschool:sync --trigger=schedule')->assertSuccessful();

    Mail::assertNotSent(MissingWorkAlert::class);
    expect(Alert::where('type', Alert::TYPE_MISSING)->whereNotNull('alert_emailed_at')->count())->toBe(1)
        ->and(Alert::where('type', Alert::TYPE_MISSING)->whereNull('alert_emailed_at')->count())->toBe(0);
});

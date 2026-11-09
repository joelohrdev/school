<?php

use App\Mail\SyncFailing;
use App\Models\SyncRun;
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
        'powerschool.digest_to' => 'parent@example.test',
    ]);

    $this->travelTo(CarbonImmutable::parse('2026-11-09 12:30:00', 'UTC'));
});

/** A run that fails before any HTTP: the login isn't configured. */
function failingSync(bool $dryRun = false): void
{
    config(['powerschool.username' => null, 'powerschool.password' => null]);
    app(SyncService::class)->run($dryRun);
    test()->travel(1)->hours();
}

function workingSync(): void
{
    config(['powerschool.username' => 'parent@example.test', 'powerschool.password' => 'secret']);
    fakePowerSchoolSync();
    app(SyncService::class)->run();
    test()->travel(1)->hours();
}

it('emails once when the second sync in a row fails', function () {
    workingSync();
    failingSync();
    Mail::assertNothingSent();

    failingSync();
    Mail::assertSent(SyncFailing::class, fn (SyncFailing $mail) => $mail->hasTo('parent@example.test')
        && $mail->failures === 2
        && $mail->lastSuccess?->status === SyncRun::STATUS_SUCCEEDED
        && str_contains((string) $mail->run->error, 'POWERSCHOOL_USERNAME'));

    failingSync();
    failingSync();
    Mail::assertSentCount(1);
});

it('emails again for a new streak after a success', function () {
    failingSync();
    failingSync();
    workingSync();
    failingSync();
    failingSync();

    Mail::assertSentCount(2);
});

it('ignores dry runs and needs a recipient', function () {
    failingSync(dryRun: true);
    failingSync(dryRun: true);
    Mail::assertNothingSent();

    config(['powerschool.digest_to' => null]);
    failingSync();
    failingSync();
    Mail::assertNothingSent();
});

it('respects a configured threshold', function () {
    config(['powerschool.failure_alert_after' => 3]);

    failingSync();
    failingSync();
    Mail::assertNothingSent();

    failingSync();
    Mail::assertSentCount(1);
});

it('renders the email without a past success', function () {
    failingSync();
    failingSync();

    Mail::assertSent(SyncFailing::class, function (SyncFailing $mail) {
        $html = $mail->render();

        return str_contains($html, 'No sync has succeeded yet')
            && str_contains($html, 'The last 2 PowerSchool syncs failed');
    });
});

<?php

use App\Models\Student;
use App\Models\SyncRun;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();

    config([
        'powerschool.base_url' => 'https://ps.example.test',
        'powerschool.username' => 'parent@example.test',
        'powerschool.password' => 'secret',
    ]);
});

it('syncs and prints a summary', function () {
    fakePowerSchoolSync();

    $this->artisan('powerschool:sync')
        ->expectsOutputToContain('Sync succeeded.')
        ->assertSuccessful();

    expect(Student::count())->toBe(2)
        ->and(SyncRun::sole()->trigger)->toBe(SyncRun::TRIGGER_CLI);
});

it('saves nothing with --dry-run', function () {
    fakePowerSchoolSync();

    $this->artisan('powerschool:sync --dry-run')
        ->expectsOutputToContain('nothing was saved')
        ->assertSuccessful();

    expect(Student::count())->toBe(0)->and(SyncRun::count())->toBe(0);
});

it('exits non-zero when the sync fails', function () {
    fakePowerSchool(['loginToPublicPortal' => 'login_failure']);

    $this->artisan('powerschool:sync')
        ->expectsOutputToContain('Invalid Username or Password!')
        ->assertFailed();
});

it('rejects unknown triggers', function () {
    $this->artisan('powerschool:sync --trigger=bogus')->assertExitCode(2);
});

it('schedules weekday syncs at 6:30 and 15:45 Chicago time without overlap', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn (Event $e) => str_contains($e->command, 'powerschool:sync --trigger=schedule'));

    expect($events->map(fn (Event $e) => $e->expression)->values()->all())->toBe(['30 6 * * 1-5', '45 15 * * 1-5'])
        ->and($events->every(fn (Event $e) => $e->timezone === 'America/Chicago' && $e->withoutOverlapping))->toBeTrue();
});

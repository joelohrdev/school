<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('model:prune')->dailyAt('03:00')->timezone(config('powerschool.timezone'));

// The database is the only copy of the grade history. See config/backup.php.
Schedule::command('backup:database')->dailyAt('02:30')->timezone(config('powerschool.timezone'));

// Weekday syncs before school and after the school day, in district time.
// The command skips itself on days in notInSessionDays when no kid has school.
// Only the morning run emails the digest, which covers everything since the last one.
foreach (['06:30' => ' --digest', '15:45' => ''] as $time => $digest) {
    Schedule::command('powerschool:sync --trigger=schedule'.$digest)
        ->weekdays()
        ->at($time)
        ->timezone(config('powerschool.timezone'))
        ->withoutOverlapping();
}

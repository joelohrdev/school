<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('model:prune')->dailyAt('03:00')->timezone(config('powerschool.timezone'));

// Weekday syncs before school and after the school day, in district time.
// Days in notInSessionDays are not skipped yet: that collection's fields are unmapped.
foreach (['06:30', '15:45'] as $time) {
    Schedule::command('powerschool:sync --trigger=schedule')
        ->weekdays()
        ->at($time)
        ->timezone(config('powerschool.timezone'))
        ->withoutOverlapping();
}

<?php

namespace App\Jobs;

use App\Models\SyncRun;
use App\Services\PowerSchool\SyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * The "Sync now" button. The queued flag is claimed atomically, so mashing
 * the button queues one sync; SyncService's own lock covers overlap with a
 * scheduled run.
 */
class SyncPowerSchoolJob implements ShouldQueue
{
    use Queueable;

    public const QUEUED_KEY = 'powerschool:sync:queued';

    public int $tries = 1;

    public int $timeout = 300;

    /**
     * Queue a sync unless one is already waiting. Returns false if one was.
     *
     * Not named queue(): Laravel's dispatcher calls a job's own queue()
     * method in place of pushing it.
     */
    public static function queueOnce(): bool
    {
        if (! Cache::add(self::QUEUED_KEY, now()->toIso8601String(), 900)) {
            return false;
        }

        self::dispatch();

        return true;
    }

    public static function isQueued(): bool
    {
        return Cache::has(self::QUEUED_KEY);
    }

    public function handle(SyncService $sync): void
    {
        try {
            $sync->run(trigger: SyncRun::TRIGGER_MANUAL);
        } finally {
            Cache::forget(self::QUEUED_KEY);
        }
    }

    public function failed(): void
    {
        Cache::forget(self::QUEUED_KEY);
    }
}

<?php

namespace App\Services\SyncHealth;

use App\Mail\SyncFailing;
use App\Models\SyncRun;
use App\Support\Recipients;
use Illuminate\Support\Facades\Mail;

/**
 * A failed sync looks the same as a quiet day from the inbox: no digest.
 * After a few failures in a row, say so once. The next streak gets its own
 * email only after a sync has succeeded in between.
 */
class FailureNotifier
{
    public function __construct(private readonly int $after) {}

    public static function fromConfig(): self
    {
        return new self(max(1, (int) config('powerschool.failure_alert_after', 2)));
    }

    /**
     * Call after a real (not dry) run finishes. Returns true when an email went out.
     */
    public function check(SyncRun $run): bool
    {
        $to = Recipients::parents();

        if ($run->dry_run || $run->status !== SyncRun::STATUS_FAILED || $to === []) {
            return false;
        }

        $streak = $this->failureStreak();

        // Exactly at the threshold, so a long outage sends one email, not one per run.
        if (count($streak) !== $this->after) {
            return false;
        }

        $lastSuccess = SyncRun::query()
            ->where('dry_run', false)
            ->where('status', SyncRun::STATUS_SUCCEEDED)
            ->latest('started_at')
            ->first();

        Mail::to($to)->send(new SyncFailing($run, count($streak), $lastSuccess));

        return true;
    }

    /**
     * Failed real runs since the most recent success, newest first.
     *
     * @return list<SyncRun>
     */
    private function failureStreak(): array
    {
        $streak = [];

        $runs = SyncRun::query()
            ->where('dry_run', false)
            ->whereIn('status', [SyncRun::STATUS_SUCCEEDED, SyncRun::STATUS_FAILED])
            ->latest('started_at')
            ->latest('id')
            ->limit($this->after + 1)
            ->get();

        foreach ($runs as $run) {
            if ($run->status !== SyncRun::STATUS_FAILED) {
                break;
            }

            $streak[] = $run;
        }

        return $streak;
    }
}

<?php

namespace App\Services\Digest;

use App\Mail\MissingWorkAlert;
use App\Models\Alert;
use App\Models\AssignmentScore;
use App\Models\Student;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails newly missing work right after the sync that found it, instead of
 * waiting for the next morning. Each alert goes out once; the morning digest
 * marks the ones it covers, so the 06:30 run doesn't send both emails.
 */
class MissingWorkNotifier
{
    public const SENT = 'sent';

    public const NOTHING_NEW = 'nothing_new';

    public const DISABLED = 'disabled';

    public const FAILED = 'failed';

    public function send(): string
    {
        $to = config('powerschool.digest_to');

        if (! config('powerschool.missing_alert_emails', true) || ! is_string($to) || $to === '') {
            return self::DISABLED;
        }

        $alerts = Alert::query()
            ->where('type', Alert::TYPE_MISSING)
            ->whereNull('alert_emailed_at')
            ->whereNull('notified_at')
            ->orderBy('id')
            ->get();

        if ($alerts->isEmpty()) {
            return self::NOTHING_NEW;
        }

        $kids = array_values(Student::query()
            ->whereIn('id', $alerts->pluck('student_id')->unique())
            ->orderBy('first_name')
            ->get()
            ->map(fn (Student $student) => [
                'name' => $student->first_name,
                'newly_missing' => array_values($alerts->where('student_id', $student->id)->map(fn (Alert $a) => $a->payload)->all()),
                'still_missing_count' => AssignmentScore::missing()->where('student_id', $student->id)->count(),
            ])
            ->all());

        try {
            Mail::to($to)->send(new MissingWorkAlert($kids));
        } catch (Throwable $e) {
            // Left unmarked, so the next sync tries again; the sync itself still counts.
            report($e);

            return self::FAILED;
        }

        Alert::whereIn('id', $alerts->modelKeys())->update(['alert_emailed_at' => now()]);

        return self::SENT;
    }
}

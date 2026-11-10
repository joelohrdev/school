<?php

namespace App\Services\Digest;

use App\Mail\MissingWorkAlert;
use App\Models\Alert;
use App\Models\AssignmentScore;
use App\Models\Student;
use App\Services\QuarterCalendar;
use App\Support\Recipients;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails newly missing work right after the sync that found it, instead of
 * waiting for the next morning. Each alert goes out once; the morning digest
 * marks the ones it covers, so the 06:30 run doesn't send both emails.
 * Only work due in the current quarter is emailed; work from an earlier
 * quarter newly marked missing is marked handled without an email.
 */
class MissingWorkNotifier
{
    public const SENT = 'sent';

    public const NOTHING_NEW = 'nothing_new';

    public const DISABLED = 'disabled';

    public const FAILED = 'failed';

    public function __construct(private readonly QuarterCalendar $quarters) {}

    public function send(): string
    {
        $to = Recipients::parents();

        if (! config('powerschool.missing_alert_emails', true) || $to === []) {
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

        $today = CarbonImmutable::now((string) config('powerschool.timezone'))->startOfDay();

        $kids = array_values(Student::query()
            ->whereIn('id', $alerts->pluck('student_id')->unique())
            ->orderBy('first_name')
            ->get()
            ->map(function (Student $student) use ($alerts, $today) {
                $quarter = $this->quarters->current($this->quarters->all($student), $today);

                return [
                    'name' => $student->first_name,
                    'quarter' => $quarter?->name,
                    'newly_missing' => array_values($alerts
                        ->where('student_id', $student->id)
                        ->filter(fn (Alert $a) => $quarter === null || $quarter->contains($a->payload['due_on'] ?? null))
                        ->map(fn (Alert $a) => $a->payload)
                        ->all()),
                    'still_missing_count' => AssignmentScore::missing()->where('student_id', $student->id)->dueIn($quarter)->count(),
                ];
            })
            ->filter(fn (array $kid) => $kid['newly_missing'] !== [])
            ->all());

        if ($kids === []) {
            // Only earlier quarters' work: handled, not emailed.
            Alert::whereIn('id', $alerts->modelKeys())->update(['alert_emailed_at' => now()]);

            return self::NOTHING_NEW;
        }

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

<?php

namespace App\Services\Digest;

use App\Mail\DailyDigest;
use App\Models\Alert;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the digest only when there is news, then marks that news as sent.
 */
class DigestSender
{
    public const SENT = 'sent';

    public const NO_CHANGES = 'no_changes';

    public const NO_RECIPIENT = 'no_recipient';

    public function __construct(private readonly DigestBuilder $builder) {}

    public function send(): string
    {
        $to = config('powerschool.digest_to');

        if (! is_string($to) || $to === '') {
            return self::NO_RECIPIENT;
        }

        $digest = $this->builder->build();

        if ($digest['alert_ids'] === []) {
            return self::NO_CHANGES;
        }

        Mail::to($to)->send(new DailyDigest($digest));

        Alert::whereIn('id', $digest['alert_ids'])->update(['notified_at' => now()]);

        // The digest lists newly missing work, so it doesn't also need its own email.
        Alert::whereIn('id', $digest['alert_ids'])
            ->where('type', Alert::TYPE_MISSING)
            ->whereNull('alert_emailed_at')
            ->update(['alert_emailed_at' => now()]);

        return self::SENT;
    }
}

<?php

namespace App\Support;

/**
 * Who the emails go to. MAIL_TO_PARENT takes one address or several,
 * separated by commas; anything that isn't an email address is skipped.
 */
final class Recipients
{
    /**
     * @return list<string>
     */
    public static function parents(): array
    {
        $value = config('powerschool.digest_to');
        $parts = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_unique(array_filter(
            array_map(fn ($a) => trim((string) $a), $parts),
            fn (string $a) => filter_var($a, FILTER_VALIDATE_EMAIL) !== false,
        )));
    }
}

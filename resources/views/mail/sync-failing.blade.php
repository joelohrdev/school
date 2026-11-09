<x-mail::message>
# Syncing has stopped working

The last {{ $failures }} PowerSchool syncs failed, so grades and missing work on the dashboard are out of date and no morning email will arrive until syncing works again.

@if ($lastSuccess)
The last successful sync was {{ $lastSuccess }}.
@else
No sync has succeeded yet.
@endif

**The most recent error:**

<x-mail::panel>
{{ $error ?? 'No error message was recorded.' }}
</x-mail::panel>

If it says the login was rejected, update `POWERSCHOOL_USERNAME` and `POWERSCHOOL_PASSWORD` in `.env`. If PowerSchool answered with an error or timed out, the district's server may be down; it usually comes back on its own.

You won't get this email again until a sync succeeds and then fails again.

<x-mail::button :url="$syncUrl">
Open sync history
</x-mail::button>
</x-mail::message>

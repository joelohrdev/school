@php
    $fmt = fn (?string $date) => $date ? \Carbon\CarbonImmutable::parse($date)->format('D M j') : 'no due date';
@endphp
<x-mail::message>
# New missing work

@foreach ($kids as $k)
## {{ $k['name'] }}

@foreach ($k['newly_missing'] as $a)
- **{{ $a['assignment_name'] }}** ({{ $a['course_name'] }}), due {{ $fmt($a['due_on'] ?? null) }}
@endforeach

{{ $k['name'] }} has {{ $k['still_missing_count'] }} missing {{ \Illuminate\Support\Str::plural('assignment', $k['still_missing_count']) }} {{ ($k['quarter'] ?? null) ? 'in '.$k['quarter'] : 'in all' }}.

@endforeach
<x-mail::button :url="$dashboardUrl">
See missing work
</x-mail::button>

You get this email when a sync finds newly missing work. The morning email still has the full picture.
</x-mail::message>

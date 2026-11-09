@php
    $fmt = fn (?string $date) => $date ? \Carbon\CarbonImmutable::parse($date)->format('D M j') : 'no due date';
    $pct = fn ($p) => $p === null ? '' : rtrim(rtrim(number_format((float) $p, 1), '0'), '.').'%';
@endphp
<x-mail::message>
# School update

@foreach ($students as $s)
## {{ $s['name'] }}

@if ($s['newly_missing'] || $s['newly_late'])
**Newly missing or late**

@foreach ($s['newly_missing'] as $a)
- **Missing:** {{ $a['assignment_name'] }} ({{ $a['course_name'] }}), due {{ $fmt($a['due_on']) }}
@endforeach
@foreach ($s['newly_late'] as $a)
- **Late:** {{ $a['assignment_name'] }} ({{ $a['course_name'] }}), due {{ $fmt($a['due_on']) }}
@endforeach

@endif
@if ($s['still_missing_count'] > 0)
@php
    $o = $s['oldest_missing'];
    $still = '**Still missing:** '.$s['still_missing_count'].' '.\Illuminate\Support\Str::plural('assignment', $s['still_missing_count']);
    if ($o) {
        $still .= '. Oldest is '.e($o['assignment_name']).' ('.e($o['course_name']).'), due '.$fmt($o['due_on'])
            .($o['days_overdue'] ? ', '.$o['days_overdue'].' '.\Illuminate\Support\Str::plural('day', $o['days_overdue']).' overdue' : '');
    }
@endphp
{!! $still !!}.

@endif
@if ($s['grade_drops'])
**Grade drops**

@foreach ($s['grade_drops'] as $g)
- {{ $g['course_name'] }}: {{ $pct($g['previous_percent']) }} → {{ $pct($g['percent']) }}@if ($g['letter_grade']) ({{ $g['letter_grade'] }})@endif
@endforeach

@endif
@if ($s['scores'])
**New scores**

@foreach ($s['scores'] as $a)
@php
    $line = e($a['assignment_name']).' ('.e($a['course_name']).'): '.e($a['score'] ?? '—');
    if ($a['points_possible']) { $line .= '/'.rtrim(rtrim(number_format($a['points_possible'], 2), '0'), '.'); }
    if ($a['percent'] !== null) { $line .= ' · '.$pct($a['percent']); }
@endphp
- {!! $line !!}
@endforeach

@endif
@if ($s['resolved'])
**Turned in or excused:** {{ collect($s['resolved'])->pluck('assignment_name')->join(', ') }}

@endif
@endforeach
<x-mail::button :url="$dashboardUrl">
Open the dashboard
</x-mail::button>
</x-mail::message>

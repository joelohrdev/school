@php
    $fmt = fn (?string $date) => $date ? \Carbon\CarbonImmutable::parse($date)->format('D M j') : 'no due date';
    $pct = fn ($p) => $p === null ? '—' : rtrim(rtrim(number_format((float) $p, 1), '0'), '.').'%';
    $signed = fn (?float $n) => $n === null ? '—' : ($n > 0 ? '+'.number_format($n, 1) : ($n < 0 ? '−'.number_format(abs($n), 1) : 'no change'));
    $verdicts = [
        'better' => 'Getting better',
        'worse' => 'Getting worse',
        'mixed' => 'Mixed',
        'steady' => 'Holding steady',
    ];
@endphp
<x-mail::message>
# School update

@foreach ($students as $s)
## {{ $s['name'] }}

@php
    $since = null;
@endphp
@if ($s['trend'])
@php
    $t = $s['trend'];
    $since = $t['since'] ? \Carbon\CarbonImmutable::parse($t['since'])->format('D M j') : null;
    $parts = [];
    if ($t['average'] !== null) {
        $parts[] = 'class average '.$pct($t['average'])
            .($since && $t['average_change'] !== null ? ' ('.($t['average_change'] == 0 ? 'no change' : ($t['average_change'] > 0 ? 'up ' : 'down ').number_format(abs($t['average_change']), 1).' points').')' : '');
    }
    $parts[] = ($t['missing'] === 0 ? 'nothing missing' : $t['missing'].' missing')
        .($since && $t['missing_before'] !== null && $t['missing_before'] !== $t['missing'] ? ' (was '.$t['missing_before'].')' : '');
    if ($t['late'] > 0 || ($t['late_before'] ?? 0) > 0) {
        $parts[] = $t['late'].' late'
            .($since && $t['late_before'] !== null && $t['late_before'] !== $t['late'] ? ' (was '.$t['late_before'].')' : '');
    }
    $summary = ($t['verdict'] && $since ? '**'.$verdicts[$t['verdict']].' since '.$since.':** ' : '**Right now:** ').implode(', ', $parts).'.';
@endphp
{!! $summary !!}

@endif
@if ($s['classes'])
<x-mail::table>
| Class | Grade | {{ $since ? 'Since '.$since : 'Change' }} |
|:------|------:|------:|
@foreach ($s['classes'] as $c)
| {{ $c['course_name'] }} | {{ trim(($c['letter_grade'] ?? '').' '.$pct($c['percent'])) }} | {{ $signed($c['change']) }} |
@endforeach
</x-mail::table>

@endif
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
- {{ $g['course_name'].': '.$pct($g['previous_percent']).' → '.$pct($g['percent']).($g['letter_grade'] ? ' ('.$g['letter_grade'].')' : '') }}
@endforeach

@endif
@if ($s['absences'])
**Attendance**

@foreach ($s['absences'] as $a)
- {{ $fmt($a['date']) }}: {{ $a['description'] ?? $a['code'] }}
@endforeach

@endif
@if ($s['comments'])
**Teacher comments**

@foreach ($s['comments'] as $a)
- {{ $a['assignment_name'] }} ({{ $a['course_name'] }}): “{{ $a['comment'] }}”
@endforeach

@endif
@if ($s['scores'])
**New scores**

@foreach ($s['scores'] as $a)
@php
    $line = e($a['assignment_name']).' ('.e($a['course_name']).'): '.e($a['score'] ?? '—');
    if ($a['points_possible']) { $line .= '/'.rtrim(rtrim(number_format($a['points_possible'], 2), '0'), '.'); }
    if ($a['percent'] !== null) { $line .= ', '.$pct($a['percent']); }
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

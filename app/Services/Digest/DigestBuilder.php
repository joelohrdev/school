<?php

namespace App\Services\Digest;

use App\Models\Alert;
use App\Models\AssignmentScore;
use App\Models\GradeSnapshot;
use App\Models\Student;
use App\Models\StudentSnapshot;
use App\Presenters\Gradebook;
use App\Services\QuarterCalendar;
use App\Support\Quarter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Gathers everything not yet emailed into one digest, grouped by kid, about
 * the current quarter only. News about work from an earlier quarter (a Q1
 * assignment finally graded, say) is left out but still marked handled.
 */
class DigestBuilder
{
    /** How far back the trend compares, in days. */
    public const TREND_DAYS = 7;

    /** A class average must move this many points to count as better or worse. */
    public const AVERAGE_STEP = 1.0;

    public function __construct(
        private readonly Gradebook $gradebook,
        private readonly QuarterCalendar $quarters,
    ) {}

    /**
     * alert_ids is everything to mark handled; shown is how many of those
     * fall in the current quarter and make it into the email.
     *
     * @return array{alert_ids: list<int>, shown: int, students: list<array<string, mixed>>, totals: array<string, int>}
     */
    public function build(): array
    {
        $today = CarbonImmutable::now((string) config('powerschool.timezone'))->startOfDay();
        $alerts = Alert::unnotified()->orderBy('id')->get()->groupBy('student_id');

        $shown = collect();

        $students = Student::orderBy('first_name')->get()->map(function (Student $student) use ($alerts, $today, &$shown) {
            $quarter = $this->quarters->current($this->quarters->all($student), $today);

            /** @var Collection<int, Alert> $mine */
            $mine = $alerts->get($student->id, collect())->filter(fn (Alert $a) => self::inQuarter($a, $quarter))->values();
            $shown = $shown->merge($mine);
            $of = fn (string $type) => $mine->where('type', $type)->map(fn (Alert $a) => $a->payload)->values()->all();

            $missing = AssignmentScore::missing()
                ->where('student_id', $student->id)
                ->dueIn($quarter)
                ->with('assignment.section')
                ->get()
                ->sortBy(fn (AssignmentScore $s) => $s->assignment->due_on?->getTimestamp() ?? PHP_INT_MAX)
                ->values();

            $oldest = $missing->first();

            return [
                'name' => $student->first_name,
                'quarter' => $quarter?->toArray(),
                'newly_missing' => $of(Alert::TYPE_MISSING),
                'newly_late' => $of(Alert::TYPE_LATE),
                'resolved' => $of(Alert::TYPE_MISSING_RESOLVED),
                'scores' => $of(Alert::TYPE_SCORE_POSTED),
                'grade_drops' => $of(Alert::TYPE_GRADE_DROP),
                'absences' => $of(Alert::TYPE_ABSENCE),
                'comments' => $of(Alert::TYPE_COMMENT),
                'still_missing_count' => $missing->count(),
                'oldest_missing' => $oldest === null ? null : [
                    'course_name' => $oldest->assignment->section->course_name,
                    'assignment_name' => $oldest->assignment->name,
                    'due_on' => $oldest->assignment->due_on?->toDateString(),
                    'days_overdue' => $oldest->assignment->due_on === null ? null : max(0, (int) $oldest->assignment->due_on->diffInDays($today)),
                ],
                'has_news' => $mine->isNotEmpty(),
                ...$this->trend($student, $today, $quarter),
            ];
        })->all();

        $all = $shown;

        return [
            'alert_ids' => array_values($alerts->flatten()->map(fn (Alert $a): int => $a->id)->all()),
            'shown' => $shown->count(),
            'students' => array_values($students),
            'totals' => [
                'newly_missing' => $all->where('type', Alert::TYPE_MISSING)->count(),
                'newly_late' => $all->where('type', Alert::TYPE_LATE)->count(),
                'grade_drops' => $all->where('type', Alert::TYPE_GRADE_DROP)->count(),
                'scores' => $all->where('type', Alert::TYPE_SCORE_POSTED)->count(),
                'resolved' => $all->where('type', Alert::TYPE_MISSING_RESOLVED)->count(),
                'absences' => $all->where('type', Alert::TYPE_ABSENCE)->count(),
                'comments' => $all->where('type', Alert::TYPE_COMMENT)->count(),
            ],
        ];
    }

    /**
     * Whether an alert is about the current quarter: work due in it, an
     * absence on a day in it, or a grade drop measured within it. Without
     * quarters, everything counts.
     */
    private static function inQuarter(Alert $alert, ?Quarter $quarter): bool
    {
        if ($quarter === null) {
            return true;
        }

        $p = $alert->payload ?? [];

        return match ($alert->type) {
            Alert::TYPE_ABSENCE => $quarter->contains($p['date'] ?? null),
            Alert::TYPE_GRADE_DROP => $quarter->contains($p['previous_on'] ?? null),
            default => $quarter->contains($p['due_on'] ?? null),
        };
    }

    /**
     * Today's numbers against the latest day at least a week back (or the
     * earliest day recorded, while there's less than a week of history),
     * never reaching back before the quarter started.
     *
     * @return array{trend: array<string, mixed>|null, classes: list<array<string, mixed>>}
     */
    private function trend(Student $student, CarbonImmutable $today, ?Quarter $quarter): array
    {
        $snapshots = fn () => StudentSnapshot::query()
            ->where('student_id', $student->id)
            ->when($quarter, fn ($q) => $q->whereDate('snapshot_on', '>=', $quarter->startsOn)->whereDate('snapshot_on', '<=', $quarter->endsOn));

        $now = $snapshots()->latest('snapshot_on')->first();

        $before = $now === null ? null : (
            $snapshots()
                ->whereDate('snapshot_on', '<=', $today->subDays(self::TREND_DAYS)->toDateString())
                ->latest('snapshot_on')
                ->first()
            ?? $snapshots()
                ->whereDate('snapshot_on', '<', $now->snapshot_on->toDateString())
                ->oldest('snapshot_on')
                ->first()
        );

        $since = $before?->snapshot_on;

        $classes = array_values(collect($this->gradebook->classes($student, $quarter))
            ->map(function (array $c) use ($since, $quarter) {
                $then = $since === null ? null : GradeSnapshot::query()
                    ->where('section_id', $c['id'])
                    ->when($quarter, fn ($q) => $q->whereDate('snapshot_on', '>=', $quarter->startsOn))
                    ->whereDate('snapshot_on', '<=', $since->toDateString())
                    ->whereNotNull('percent')
                    ->latest('snapshot_on')
                    ->value('percent');

                return [
                    'course_name' => $c['course'],
                    'percent' => $c['percent'],
                    'letter_grade' => $c['letter'],
                    'change' => $then === null || $c['percent'] === null ? null : round($c['percent'] - (float) $then, 1),
                ];
            })
            ->filter(fn (array $c) => $c['percent'] !== null || $c['letter_grade'] !== null)
            ->all());

        if ($now === null) {
            return ['trend' => null, 'classes' => $classes];
        }

        $average = $now->average_percent === null ? null : (float) $now->average_percent;
        $averageBefore = $before?->average_percent === null ? null : (float) $before->average_percent;

        // With quarters, count only work due this quarter. Days saved before
        // quarter counts existed have no comparison to offer.
        [$missing, $late, $missingBefore, $lateBefore] = $quarter === null
            ? [$now->missing_count, $now->late_count, $before?->missing_count, $before?->late_count]
            : [
                AssignmentScore::missing()->where('student_id', $student->id)->dueIn($quarter)->count(),
                AssignmentScore::late()->where('student_id', $student->id)->dueIn($quarter)->count(),
                $before?->quarter_missing_count,
                $before?->quarter_late_count,
            ];

        return [
            'trend' => [
                'since' => $since?->toDateString(),
                'average' => $average,
                'average_change' => $average === null || $averageBefore === null ? null : round($average - $averageBefore, 1),
                'missing' => $missing,
                'missing_before' => $missingBefore,
                'late' => $late,
                'late_before' => $lateBefore,
                'verdict' => $before === null ? null : self::verdict(
                    $average === null || $averageBefore === null ? 0.0 : $average - $averageBefore,
                    $missingBefore === null ? 0 : $missing - $missingBefore,
                ),
            ],
            'classes' => $classes,
        ];
    }

    /**
     * Grades up or missing work down is better; grades down or missing work
     * up is worse; one of each is mixed.
     */
    public static function verdict(float $averageChange, int $missingChange): string
    {
        $grades = $averageChange >= self::AVERAGE_STEP ? 1 : ($averageChange <= -self::AVERAGE_STEP ? -1 : 0);
        $missing = $missingChange < 0 ? 1 : ($missingChange > 0 ? -1 : 0);

        return match (true) {
            $grades === 0 && $missing === 0 => 'steady',
            $grades >= 0 && $missing >= 0 => 'better',
            $grades <= 0 && $missing <= 0 => 'worse',
            default => 'mixed',
        };
    }
}

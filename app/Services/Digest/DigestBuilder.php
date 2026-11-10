<?php

namespace App\Services\Digest;

use App\Models\Alert;
use App\Models\AssignmentScore;
use App\Models\GradeSnapshot;
use App\Models\Student;
use App\Models\StudentSnapshot;
use App\Presenters\Gradebook;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Gathers everything not yet emailed into one digest, grouped by kid.
 */
class DigestBuilder
{
    /** How far back the trend compares, in days. */
    public const TREND_DAYS = 7;

    /** A class average must move this many points to count as better or worse. */
    public const AVERAGE_STEP = 1.0;

    public function __construct(private readonly Gradebook $gradebook) {}

    /**
     * @return array{alert_ids: list<int>, students: list<array<string, mixed>>, totals: array<string, int>}
     */
    public function build(): array
    {
        $today = CarbonImmutable::now((string) config('powerschool.timezone'))->startOfDay();
        $alerts = Alert::unnotified()->orderBy('id')->get()->groupBy('student_id');

        $students = Student::orderBy('first_name')->get()->map(function (Student $student) use ($alerts, $today) {
            /** @var Collection<int, Alert> $mine */
            $mine = $alerts->get($student->id, collect());
            $of = fn (string $type) => $mine->where('type', $type)->map(fn (Alert $a) => $a->payload)->values()->all();

            $missing = AssignmentScore::missing()
                ->where('student_id', $student->id)
                ->with('assignment.section')
                ->get()
                ->sortBy(fn (AssignmentScore $s) => $s->assignment->due_on?->getTimestamp() ?? PHP_INT_MAX)
                ->values();

            $oldest = $missing->first();

            return [
                'name' => $student->first_name,
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
                ...$this->trend($student, $today),
            ];
        })->all();

        $all = $alerts->flatten();

        return [
            'alert_ids' => array_values($all->map(fn (Alert $a): int => $a->id)->all()),
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
     * Today's numbers against the latest day at least a week back (or the
     * earliest day recorded, while there's less than a week of history).
     *
     * @return array{trend: array<string, mixed>|null, classes: list<array<string, mixed>>}
     */
    private function trend(Student $student, CarbonImmutable $today): array
    {
        $now = StudentSnapshot::query()->where('student_id', $student->id)->latest('snapshot_on')->first();

        $before = $now === null ? null : (
            StudentSnapshot::query()
                ->where('student_id', $student->id)
                ->whereDate('snapshot_on', '<=', $today->subDays(self::TREND_DAYS)->toDateString())
                ->latest('snapshot_on')
                ->first()
            ?? StudentSnapshot::query()
                ->where('student_id', $student->id)
                ->whereDate('snapshot_on', '<', $now->snapshot_on->toDateString())
                ->oldest('snapshot_on')
                ->first()
        );

        $since = $before?->snapshot_on;

        $classes = array_values(collect($this->gradebook->classes($student))
            ->map(function (array $c) use ($since) {
                $then = $since === null ? null : GradeSnapshot::query()
                    ->where('section_id', $c['id'])
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

        return [
            'trend' => [
                'since' => $since?->toDateString(),
                'average' => $average,
                'average_change' => $average === null || $averageBefore === null ? null : round($average - $averageBefore, 1),
                'missing' => $now->missing_count,
                'missing_before' => $before?->missing_count,
                'late' => $now->late_count,
                'late_before' => $before?->late_count,
                'verdict' => $before === null ? null : self::verdict(
                    $average === null || $averageBefore === null ? 0.0 : $average - $averageBefore,
                    $now->missing_count - $before->missing_count,
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

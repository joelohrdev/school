<?php

namespace App\Presenters;

use App\Models\Assignment;
use App\Models\AssignmentScore;
use App\Models\AttendanceRecord;
use App\Models\GradeSnapshot;
use App\Models\Section;
use App\Models\Student;
use App\Models\SyncRun;
use App\Services\PowerSchool\CurrentGradeResolver;
use Carbon\CarbonImmutable;

/**
 * Shapes gradebook data into the props the Inertia pages render.
 */
class Gradebook
{
    public const TREND_DAYS = 30;

    public function __construct(private readonly CurrentGradeResolver $grades) {}

    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now((string) config('powerschool.timezone'))->startOfDay();
    }

    /**
     * @return array<string, mixed>
     */
    public function student(Student $student): array
    {
        return [
            'id' => $student->id,
            'name' => $student->first_name,
            'fullName' => $student->fullName(),
            'gradeLevel' => $student->grade_level,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(Student $student): array
    {
        $missing = $this->missingWork($student);

        return [
            ...$this->student($student),
            'missingCount' => count($missing),
            'lateCount' => AssignmentScore::late()->where('student_id', $student->id)->count(),
            'missing' => $missing,
            'classes' => $this->classes($student),
        ];
    }

    /**
     * Current grade and recent trend per class, in period order.
     *
     * @return list<array<string, mixed>>
     */
    public function classes(Student $student): array
    {
        $since = $this->today()->subDays(self::TREND_DAYS)->toDateString();

        return array_values($student->sections()
            ->with([
                'teacher',
                'finalGrades.reportingTerm',
                'gradeSnapshots' => fn ($q) => $q->whereDate('snapshot_on', '>=', $since)->orderBy('snapshot_on'),
            ])
            ->orderByRaw('period_sort is null, period_sort')
            ->orderBy('course_name')
            ->get()
            ->map(fn (Section $section) => $this->sectionRow($section))
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function sectionRow(Section $section): array
    {
        $current = $this->grades->pick($section->finalGrades, $this->today());

        return [
            'id' => $section->id,
            'course' => $section->course_name,
            'period' => $section->period,
            'room' => $section->room,
            'teacher' => $section->teacher?->fullName(),
            'teacherEmail' => $section->teacher?->email,
            'percent' => $current?->percent === null ? null : (float) $current->percent,
            'letter' => $current?->letter_grade,
            'term' => $current?->reportingTerm?->abbreviation,
            'trend' => $section->gradeSnapshots
                ->map(fn (GradeSnapshot $s) => ['date' => $s->snapshot_on->toDateString(), 'percent' => $s->percent === null ? null : (float) $s->percent])
                ->values()
                ->all(),
        ];
    }

    /**
     * Missing, non-exempt work, oldest due date first.
     *
     * @return list<array<string, mixed>>
     */
    public function missingWork(Student $student): array
    {
        $today = $this->today();

        return array_values(AssignmentScore::missing()
            ->where('student_id', $student->id)
            ->with('assignment.section')
            ->get()
            ->sortBy(fn (AssignmentScore $s) => $s->assignment->due_on?->getTimestamp() ?? PHP_INT_MAX)
            ->map(fn (AssignmentScore $s) => [
                'id' => $s->assignment->id,
                'name' => $s->assignment->name,
                'course' => $s->assignment->section->course_name,
                'sectionId' => $s->assignment->section_id,
                'dueOn' => $s->assignment->due_on?->toDateString(),
                'daysOverdue' => $s->assignment->due_on === null ? null : max(0, (int) $s->assignment->due_on->diffInDays($today, false)),
            ])
            ->all());
    }

    /**
     * Every assignment with its score, newest due date first.
     *
     * @return list<array<string, mixed>>
     */
    public function assignments(Student $student, ?Section $section = null): array
    {
        $sectionIds = $section ? [$section->id] : $student->sections()->pluck('id')->all();

        return array_values(Assignment::query()
            ->whereIn('section_id', $sectionIds)
            ->with(['section', 'category', 'score'])
            ->orderByRaw('due_on is null, due_on desc')
            ->orderBy('name')
            ->get()
            ->map(fn (Assignment $a) => $this->assignmentRow($a))
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    public function assignmentRow(Assignment $a): array
    {
        $score = $a->score;

        return [
            'id' => $a->id,
            'name' => $a->name,
            'description' => $a->description,
            'course' => $a->section->course_name,
            'sectionId' => $a->section_id,
            'category' => $a->category?->name,
            'dueOn' => $a->due_on?->toDateString(),
            'pointsPossible' => $a->points_possible === null ? null : (float) $a->points_possible,
            'score' => $score?->score,
            'percent' => $score?->percent === null ? null : (float) $score->percent,
            'letter' => $score?->letter_grade,
            'missing' => (bool) $score?->missing,
            'late' => (bool) $score?->late,
            'exempt' => (bool) $score?->exempt,
            'collected' => (bool) $score?->collected,
            'comment' => $score?->comment,
            'scoredAt' => $score?->scored_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{total: int, byCode: list<array{code: string|null, description: string|null, count: int}>, recent: list<array{date: string, code: string|null, description: string|null}>}
     */
    public function attendance(Student $student): array
    {
        $marked = AttendanceRecord::marked()->where('student_id', $student->id)->orderByDesc('date')->get();

        return [
            'total' => $marked->count(),
            'byCode' => $marked->groupBy(fn (AttendanceRecord $r) => $r->code.'|'.$r->description)
                ->map(fn ($group) => [
                    'code' => $group->first()->code,
                    'description' => $group->first()->description,
                    'count' => $group->count(),
                ])
                ->sortByDesc('count')
                ->values()
                ->pipe(fn ($c) => array_values($c->all())),
            'recent' => $marked->take(20)
                ->map(fn (AttendanceRecord $r) => ['date' => $r->date->toDateString(), 'code' => $r->code, 'description' => $r->description])
                ->pipe(fn ($c) => array_values($c->all())),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function syncRun(?SyncRun $run): ?array
    {
        if ($run === null) {
            return null;
        }

        $totals = collect($run->counts ?? [])->reduce(fn (array $t, array $c) => [
            'created' => $t['created'] + ($c['created'] ?? 0),
            'updated' => $t['updated'] + ($c['updated'] ?? 0),
            'skipped' => $t['skipped'] + ($c['skipped'] ?? 0),
        ], ['created' => 0, 'updated' => 0, 'skipped' => 0]);

        return [
            'id' => $run->id,
            'status' => $run->status,
            'trigger' => $run->trigger,
            'dryRun' => $run->dry_run,
            'startedAt' => $run->started_at->toIso8601String(),
            'finishedAt' => $run->finished_at?->toIso8601String(),
            'durationSeconds' => $run->finished_at ? (int) $run->started_at->diffInSeconds($run->finished_at) : null,
            'totals' => $totals,
            'alerts' => $run->counts['alerts']['created'] ?? 0,
            'error' => $run->error,
        ];
    }

    public function lastSync(): ?SyncRun
    {
        return SyncRun::query()->where('dry_run', false)->latest('started_at')->latest('id')->first();
    }
}

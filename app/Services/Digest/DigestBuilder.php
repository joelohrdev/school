<?php

namespace App\Services\Digest;

use App\Models\Alert;
use App\Models\AssignmentScore;
use App\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Gathers everything not yet emailed into one digest, grouped by kid.
 */
class DigestBuilder
{
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
                'still_missing_count' => $missing->count(),
                'oldest_missing' => $oldest === null ? null : [
                    'course_name' => $oldest->assignment->section->course_name,
                    'assignment_name' => $oldest->assignment->name,
                    'due_on' => $oldest->assignment->due_on?->toDateString(),
                    'days_overdue' => $oldest->assignment->due_on === null ? null : max(0, (int) $oldest->assignment->due_on->diffInDays($today)),
                ],
                'has_news' => $mine->isNotEmpty(),
            ];
        })->filter(fn (array $s) => $s['has_news'] || $s['still_missing_count'] > 0)->values()->all();

        $all = $alerts->flatten();

        return [
            'alert_ids' => $all->pluck('id')->all(),
            'students' => $students,
            'totals' => [
                'newly_missing' => $all->where('type', Alert::TYPE_MISSING)->count(),
                'newly_late' => $all->where('type', Alert::TYPE_LATE)->count(),
                'grade_drops' => $all->where('type', Alert::TYPE_GRADE_DROP)->count(),
                'scores' => $all->where('type', Alert::TYPE_SCORE_POSTED)->count(),
                'resolved' => $all->where('type', Alert::TYPE_MISSING_RESOLVED)->count(),
            ],
        ];
    }
}

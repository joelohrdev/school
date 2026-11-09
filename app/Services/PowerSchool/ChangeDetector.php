<?php

namespace App\Services\PowerSchool;

use App\Models\Alert;
use App\Models\AssignmentScore;
use App\Models\FinalGrade;
use App\Models\GradeSnapshot;
use App\Models\Section;
use App\Models\Student;
use App\Models\SyncRun;

/**
 * Compares a record's state before and after a sync and raises alerts.
 *
 * A kid's very first sync is a baseline: everything already in PowerSchool
 * is history, not news, so nothing is raised for it.
 */
class ChangeDetector
{
    public function __construct(
        private readonly float $gradeDropThreshold,
    ) {}

    public static function fromConfig(): self
    {
        return new self((float) config('powerschool.grade_drop_threshold', 3));
    }

    /**
     * Snapshot the fields detection cares about, before the row is updated.
     *
     * @return array{missing: bool, late: bool, scored: bool}|null null for a new row
     */
    public function scoreState(AssignmentScore $score): ?array
    {
        if (! $score->exists) {
            return null;
        }

        return [
            'missing' => (bool) $score->getOriginal('missing') && ! $score->getOriginal('exempt'),
            'late' => (bool) $score->getOriginal('late') && ! $score->getOriginal('exempt'),
            'scored' => self::isScored($score->getOriginal('score'), $score->getOriginal('percent'), $score->getOriginal('letter_grade')),
        ];
    }

    /**
     * @param  array{missing: bool, late: bool, scored: bool}|null  $before
     * @return list<Alert>
     */
    public function scoreChanged(Student $student, AssignmentScore $score, ?array $before, SyncRun $run, bool $baseline): array
    {
        if ($baseline) {
            return [];
        }

        $before ??= ['missing' => false, 'late' => false, 'scored' => false];
        $missing = $score->missing && ! $score->exempt;
        $late = $score->late && ! $score->exempt;
        $scored = self::isScored($score->score, $score->percent, $score->letter_grade);

        $types = array_keys(array_filter([
            Alert::TYPE_MISSING => $missing && ! $before['missing'],
            Alert::TYPE_MISSING_RESOLVED => ! $missing && $before['missing'],
            Alert::TYPE_LATE => $late && ! $before['late'],
            Alert::TYPE_SCORE_POSTED => $scored && ! $before['scored'],
        ]));

        if ($types === []) {
            return [];
        }

        $score->loadMissing('assignment.section');
        $assignment = $score->assignment;

        $payload = [
            'section_id' => $assignment->section_id,
            'course_name' => $assignment->section->course_name,
            'assignment_id' => $assignment->id,
            'assignment_name' => $assignment->name,
            'due_on' => $assignment->due_on?->toDateString(),
            'score' => $score->score,
            'percent' => $score->percent === null ? null : (float) $score->percent,
            'letter_grade' => $score->letter_grade,
            'points_possible' => $assignment->points_possible === null ? null : (float) $assignment->points_possible,
        ];

        return array_map(fn (string $type) => Alert::create([
            'student_id' => $student->id,
            'sync_run_id' => $run->id,
            'type' => $type,
            'payload' => $payload,
        ]), $types);
    }

    /**
     * Raise a grade-drop alert when the class's current percent fell by at
     * least the threshold since the most recent snapshot (which may be this
     * morning's, so a drop is reported once, not on every run).
     */
    public function gradeChanged(Student $student, Section $section, ?GradeSnapshot $previous, FinalGrade $current, SyncRun $run, bool $baseline): ?Alert
    {
        if ($baseline || $previous?->percent === null || $current->percent === null) {
            return null;
        }

        $drop = round((float) $previous->percent - (float) $current->percent, 2);

        if ($drop < $this->gradeDropThreshold) {
            return null;
        }

        return Alert::create([
            'student_id' => $student->id,
            'sync_run_id' => $run->id,
            'type' => Alert::TYPE_GRADE_DROP,
            'payload' => [
                'section_id' => $section->id,
                'course_name' => $section->course_name,
                'previous_percent' => (float) $previous->percent,
                'previous_on' => $previous->snapshot_on->toDateString(),
                'percent' => (float) $current->percent,
                'letter_grade' => $current->letter_grade,
                'drop' => $drop,
            ],
        ]);
    }

    private static function isScored(mixed $score, mixed $percent, mixed $letter): bool
    {
        return $score !== null || $percent !== null || $letter !== null;
    }
}

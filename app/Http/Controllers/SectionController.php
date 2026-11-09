<?php

namespace App\Http\Controllers;

use App\Models\GradeSnapshot;
use App\Models\Section;
use App\Presenters\Gradebook;
use Inertia\Inertia;
use Inertia\Response;

class SectionController extends Controller
{
    public function show(Section $section, Gradebook $gradebook): Response
    {
        $section->load(['student', 'teacher', 'finalGrades.reportingTerm', 'gradeSnapshots']);

        $row = $gradebook->sectionRow($section);

        return Inertia::render('sections/show', [
            'student' => $gradebook->student($section->student),
            'section' => [
                ...$row,
                'history' => $section->gradeSnapshots
                    ->sortBy('snapshot_on')
                    ->map(fn (GradeSnapshot $s) => ['date' => $s->snapshot_on->toDateString(), 'percent' => $s->percent === null ? null : (float) $s->percent])
                    ->values(),
            ],
            'reportCard' => $gradebook->reportCard($section),
            'assignments' => $gradebook->assignments($section->student, $section),
            'today' => $gradebook->today()->toDateString(),
        ]);
    }
}

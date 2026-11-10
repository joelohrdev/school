<?php

namespace App\Http\Controllers;

use App\Models\GradeSnapshot;
use App\Models\Section;
use App\Presenters\Gradebook;
use App\Services\QuarterCalendar;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SectionController extends Controller
{
    public function show(Request $request, Section $section, Gradebook $gradebook, QuarterCalendar $calendar): Response
    {
        $section->load(['student', 'teacher', 'finalGrades.reportingTerm', 'gradeSnapshots']);

        $today = $gradebook->today();
        $quarters = $calendar->all($section->student);
        $quarter = $calendar->select($quarters, $request->string('quarter')->toString() ?: null, $today);

        $row = $gradebook->sectionRow($section, $quarter);

        return Inertia::render('sections/show', [
            'student' => $gradebook->student($section->student),
            'quarters' => $calendar->picker($quarters, $quarter, $today),
            'section' => [
                ...$row,
                'history' => $section->gradeSnapshots
                    ->when($quarter, fn ($c) => $c->filter(fn (GradeSnapshot $s) => $quarter->contains($s->snapshot_on)))
                    ->sortBy('snapshot_on')
                    ->map(fn (GradeSnapshot $s) => ['date' => $s->snapshot_on->toDateString(), 'percent' => $s->percent === null ? null : (float) $s->percent])
                    ->values(),
            ],
            'reportCard' => $gradebook->reportCard($section),
            'assignments' => $gradebook->assignments($section->student, $section, $quarter),
            'today' => $today->toDateString(),
        ]);
    }
}

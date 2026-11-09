<?php

namespace App\Services\PowerSchool;

use App\Models\FinalGrade;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Picks the grade that counts as a class's "current grade" today.
 *
 * A section carries one final grade per reporting term (Q1, Q2, S1, …).
 * The current one is the graded term that contains today, preferring the
 * shortest (a quarter over its semester). Outside any term, it falls back to
 * the most recently stored grade.
 */
class CurrentGradeResolver
{
    /**
     * @param  Collection<int, FinalGrade>  $grades  with reportingTerm loaded
     */
    public function pick(Collection $grades, CarbonInterface $today): ?FinalGrade
    {
        $graded = $grades->filter(fn (FinalGrade $g): bool => $g->percent !== null || $g->letter_grade !== null);

        $inProgress = $graded
            ->filter(function (FinalGrade $g) use ($today): bool {
                $term = $g->reportingTerm;

                return $term?->starts_on !== null
                    && $term->ends_on !== null
                    && $today->betweenIncluded($term->starts_on->startOfDay(), $term->ends_on->endOfDay());
            })
            ->sortBy(fn (FinalGrade $g): float => $g->reportingTerm->starts_on->diffInDays($g->reportingTerm->ends_on));

        return $inProgress->first()
            ?? $graded->sortByDesc(fn (FinalGrade $g): int => $g->as_of?->getTimestamp() ?? 0)->first();
    }
}

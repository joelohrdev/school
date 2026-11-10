<?php

namespace App\Services;

use App\Models\FinalGrade;
use App\Models\ReportingTerm;
use App\Models\SchoolQuarter;
use App\Models\Student;
use App\Support\Quarter;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The school's quarters. PowerSchool's own quarter reporting terms (Q1–Q4)
 * come first; dates entered in settings are the fallback when it sends none.
 */
class QuarterCalendar
{
    public const SOURCE_POWERSCHOOL = 'powerschool';

    public const SOURCE_MANUAL = 'manual';

    public const WHOLE_YEAR = 'year';

    /**
     * Quarters in date order. With a kid, prefer the terms their own grades
     * are filed under, in case two schools keep different calendars.
     *
     * @return Collection<int, Quarter>
     */
    public function all(?Student $student = null): Collection
    {
        $terms = $this->powerSchoolTerms();

        if ($student !== null) {
            $used = FinalGrade::query()->where('student_id', $student->id)->distinct()->pluck('reporting_term_id')->filter()->all();
            $own = $terms->filter(fn (ReportingTerm $t) => in_array($t->id, $used, true));

            if ($own->isNotEmpty()) {
                $terms = $own;
            }
        }

        if ($terms->isNotEmpty()) {
            return $terms
                ->unique(fn (ReportingTerm $t) => strtoupper((string) $t->abbreviation))
                ->map(fn (ReportingTerm $t) => Quarter::fromDates(strtoupper((string) $t->abbreviation), $t->starts_on, $t->ends_on, self::SOURCE_POWERSCHOOL))
                ->values();
        }

        return $this->manual();
    }

    public function fromPowerSchool(): bool
    {
        return $this->powerSchoolTerms()->isNotEmpty();
    }

    /** @return Collection<int, Quarter> */
    public function manual(): Collection
    {
        return SchoolQuarter::query()
            ->orderBy('starts_on')
            ->get()
            ->map(fn (SchoolQuarter $q) => Quarter::fromDates($q->name, $q->starts_on, $q->ends_on, self::SOURCE_MANUAL))
            ->values();
    }

    /**
     * The quarter today falls in; between quarters, the one that ended last;
     * before the first, the first.
     *
     * @param  Collection<int, Quarter>  $quarters
     */
    public function current(Collection $quarters, CarbonInterface $today): ?Quarter
    {
        return $quarters->first(fn (Quarter $q) => $q->contains($today))
            ?? $quarters->filter(fn (Quarter $q) => $q->hasStarted($today))->last()
            ?? $quarters->first();
    }

    /**
     * The quarter a page asked for: a quarter's key, "year" for no quarter,
     * and anything else (or nothing) for the current one.
     *
     * @param  Collection<int, Quarter>  $quarters
     */
    public function select(Collection $quarters, ?string $key, CarbonInterface $today): ?Quarter
    {
        if ($key === self::WHOLE_YEAR) {
            return null;
        }

        return $quarters->first(fn (Quarter $q) => $q->key() === $key) ?? $this->current($quarters, $today);
    }

    /**
     * Props for the quarter picker. Quarters that haven't started are left
     * off; there's nothing in them yet.
     *
     * @param  Collection<int, Quarter>  $quarters
     * @return array{options: list<array{key: string, name: string, startsOn: string, endsOn: string}>, selected: string}
     */
    public function picker(Collection $quarters, ?Quarter $selected, CarbonInterface $today): array
    {
        return [
            'options' => array_values($quarters
                ->filter(fn (Quarter $q) => $q->hasStarted($today) || $q === $selected)
                ->map(fn (Quarter $q) => $q->toArray())
                ->all()),
            'selected' => $selected?->key() ?? self::WHOLE_YEAR,
        ];
    }

    /** @return Collection<int, ReportingTerm> */
    private function powerSchoolTerms(): Collection
    {
        return ReportingTerm::query()
            ->whereNotNull('starts_on')
            ->whereNotNull('ends_on')
            ->orderBy('starts_on')
            ->orderBy('id')
            ->get()
            ->filter(fn (ReportingTerm $t) => preg_match('/^Q\d$/i', trim((string) $t->abbreviation)) === 1)
            ->values();
    }
}

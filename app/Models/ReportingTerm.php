<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $ps_id
 * @property int|null $ps_term_id
 * @property int|null $term_id
 * @property string|null $abbreviation
 * @property string|null $title
 * @property CarbonImmutable|null $starts_on
 * @property CarbonImmutable|null $ends_on
 * @property int|null $sort_order
 * @property bool $suppress_grades
 * @property bool $suppress_percents
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['ps_id', 'ps_term_id', 'term_id', 'abbreviation', 'title', 'starts_on', 'ends_on', 'sort_order', 'suppress_grades', 'suppress_percents'])]
class ReportingTerm extends Model
{
    protected function casts(): array
    {
        return [
            'ps_id' => 'integer',
            'ps_term_id' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'sort_order' => 'integer',
            'suppress_grades' => 'boolean',
            'suppress_percents' => 'boolean',
        ];
    }

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /** @return HasMany<FinalGrade, $this> */
    public function finalGrades(): HasMany
    {
        return $this->hasMany(FinalGrade::class);
    }
}

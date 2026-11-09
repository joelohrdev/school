<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

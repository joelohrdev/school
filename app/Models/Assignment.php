<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['section_id', 'assignment_category_id', 'ps_id', 'ps_assignment_id', 'name', 'description', 'due_on', 'points_possible', 'weight', 'counts_in_final_grade', 'scores_published'])]
class Assignment extends Model
{
    protected function casts(): array
    {
        return [
            'ps_id' => 'integer',
            'ps_assignment_id' => 'integer',
            'due_on' => 'date',
            'points_possible' => 'decimal:2',
            'weight' => 'decimal:2',
            'counts_in_final_grade' => 'boolean',
            'scores_published' => 'boolean',
        ];
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return BelongsTo<AssignmentCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssignmentCategory::class, 'assignment_category_id');
    }

    /**
     * Sections are per student, so an assignment has at most one score.
     *
     * @return HasOne<AssignmentScore, $this>
     */
    public function score(): HasOne
    {
        return $this->hasOne(AssignmentScore::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_id', 'assignment_id', 'ps_id', 'score', 'percent', 'letter_grade', 'missing', 'late', 'exempt', 'collected', 'incomplete', 'comment', 'scored_at'])]
class AssignmentScore extends Model
{
    protected function casts(): array
    {
        return [
            'ps_id' => 'integer',
            'percent' => 'decimal:2',
            'missing' => 'boolean',
            'late' => 'boolean',
            'exempt' => 'boolean',
            'collected' => 'boolean',
            'incomplete' => 'boolean',
            'scored_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Assignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /** @param Builder<self> $query */
    public function scopeMissing(Builder $query): void
    {
        $query->where('missing', true)->where('exempt', false);
    }

    /** @param Builder<self> $query */
    public function scopeLate(Builder $query): void
    {
        $query->where('late', true)->where('exempt', false);
    }
}

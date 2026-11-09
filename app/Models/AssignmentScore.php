<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $student_id
 * @property int $assignment_id
 * @property int $ps_id
 * @property string|null $score
 * @property string|null $percent
 * @property string|null $letter_grade
 * @property bool $missing
 * @property bool $late
 * @property bool $exempt
 * @property bool $collected
 * @property bool $incomplete
 * @property string|null $comment
 * @property CarbonImmutable|null $scored_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
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

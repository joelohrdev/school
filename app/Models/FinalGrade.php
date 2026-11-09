<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $student_id
 * @property int $section_id
 * @property int|null $reporting_term_id
 * @property int $ps_id
 * @property string|null $percent
 * @property string|null $letter_grade
 * @property CarbonImmutable|null $as_of
 * @property string|null $store_type
 * @property string|null $comment
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['student_id', 'section_id', 'reporting_term_id', 'ps_id', 'percent', 'letter_grade', 'as_of', 'store_type', 'comment'])]
class FinalGrade extends Model
{
    protected function casts(): array
    {
        return [
            'ps_id' => 'integer',
            'percent' => 'decimal:2',
            'as_of' => 'date',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Section, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** @return BelongsTo<ReportingTerm, $this> */
    public function reportingTerm(): BelongsTo
    {
        return $this->belongsTo(ReportingTerm::class);
    }
}

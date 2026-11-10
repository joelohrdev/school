<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A kid's headline numbers on one day: what the morning email compares
 * against to say whether things are getting better or worse.
 *
 * @property int $id
 * @property int $student_id
 * @property CarbonImmutable $snapshot_on
 * @property int $missing_count
 * @property int $late_count
 * @property int|null $quarter_missing_count missing work due in that day's quarter
 * @property int|null $quarter_late_count
 * @property string|null $average_percent mean of the current class grades
 * @property int $graded_classes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['student_id', 'snapshot_on', 'missing_count', 'late_count', 'quarter_missing_count', 'quarter_late_count', 'average_percent', 'graded_classes'])]
class StudentSnapshot extends Model
{
    protected function casts(): array
    {
        return [
            'snapshot_on' => 'date',
            'missing_count' => 'integer',
            'late_count' => 'integer',
            'quarter_missing_count' => 'integer',
            'quarter_late_count' => 'integer',
            'graded_classes' => 'integer',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}

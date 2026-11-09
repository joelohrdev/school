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
 * @property int $ps_id
 * @property CarbonImmutable $date
 * @property string|null $code blank means present
 * @property string|null $description
 * @property int|null $ps_period_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['student_id', 'ps_id', 'date', 'code', 'description', 'ps_period_id'])]
class AttendanceRecord extends Model
{
    protected function casts(): array
    {
        return [
            'ps_id' => 'integer',
            'date' => 'date',
            'ps_period_id' => 'integer',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Records with an attendance code (absences, tardies, …), not plain presence.
     *
     * @param  Builder<self>  $query
     */
    public function scopeMarked(Builder $query): void
    {
        $query->whereNotNull('code')->where('code', '!=', '');
    }
}

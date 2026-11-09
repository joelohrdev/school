<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $ps_id
 * @property int|null $ps_dcid
 * @property string $first_name
 * @property string|null $middle_name
 * @property string $last_name
 * @property int|null $grade_level
 * @property string|null $current_term
 * @property string|null $current_gpa
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['ps_id', 'ps_dcid', 'first_name', 'middle_name', 'last_name', 'grade_level', 'current_term', 'current_gpa'])]
class Student extends Model
{
    protected function casts(): array
    {
        return [
            'ps_id' => 'integer',
            'ps_dcid' => 'integer',
            'grade_level' => 'integer',
        ];
    }

    /** @return HasMany<Section, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    /** @return HasMany<AssignmentScore, $this> */
    public function assignmentScores(): HasMany
    {
        return $this->hasMany(AssignmentScore::class);
    }

    /** @return HasMany<FinalGrade, $this> */
    public function finalGrades(): HasMany
    {
        return $this->hasMany(FinalGrade::class);
    }

    /** @return HasMany<GradeSnapshot, $this> */
    public function gradeSnapshots(): HasMany
    {
        return $this->hasMany(GradeSnapshot::class);
    }

    /** @return HasMany<Alert, $this> */
    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    /** @return HasMany<AttendanceRecord, $this> */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /** @return HasMany<RawPayload, $this> */
    public function rawPayloads(): HasMany
    {
        return $this->hasMany(RawPayload::class);
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }
}

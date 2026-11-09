<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $student_id
 * @property int|null $teacher_id
 * @property int|null $term_id
 * @property int $ps_id
 * @property int|null $ps_dcid
 * @property int|null $ps_term_id
 * @property string $course_name
 * @property string|null $course_code
 * @property string|null $section_number
 * @property string|null $period
 * @property int|null $period_sort
 * @property string|null $room
 * @property int|null $school_number
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['student_id', 'teacher_id', 'term_id', 'ps_id', 'ps_dcid', 'ps_term_id', 'course_name', 'course_code', 'section_number', 'period', 'period_sort', 'room', 'school_number'])]
class Section extends Model
{
    protected function casts(): array
    {
        return [
            'ps_id' => 'integer',
            'ps_dcid' => 'integer',
            'ps_term_id' => 'integer',
            'period_sort' => 'integer',
            'school_number' => 'integer',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Teacher, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /** @return HasMany<Assignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
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
}

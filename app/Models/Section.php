<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

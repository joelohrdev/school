<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Both alerts (missing, late, grade drop, absence) and informational events
 * (missing resolved, score posted, teacher comment) live here, told apart by type.
 *
 * @property int $id
 * @property int $student_id
 * @property int|null $sync_run_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable|null $notified_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['student_id', 'sync_run_id', 'type', 'payload', 'read_at', 'notified_at'])]
class Alert extends Model
{
    public const TYPE_MISSING = 'missing';

    public const TYPE_LATE = 'late';

    public const TYPE_MISSING_RESOLVED = 'missing_resolved';

    public const TYPE_SCORE_POSTED = 'score_posted';

    public const TYPE_GRADE_DROP = 'grade_drop';

    public const TYPE_ABSENCE = 'absence';

    public const TYPE_COMMENT = 'comment';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'read_at' => 'datetime',
            'notified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<SyncRun, $this> */
    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(SyncRun::class);
    }

    /** @param Builder<self> $query */
    public function scopeUnread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    /** @param Builder<self> $query */
    public function scopeUnnotified(Builder $query): void
    {
        $query->whereNull('notified_at');
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }
}

<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The studentDataVO as received, kept encrypted for debugging and pruned
 * after 14 days by model:prune.
 *
 * @property int $id
 * @property int $student_id
 * @property int|null $sync_run_id
 * @property array<string, mixed> $payload
 * @property CarbonImmutable $created_at
 */
#[Fillable(['student_id', 'sync_run_id', 'payload'])]
class RawPayload extends Model
{
    use MassPrunable;

    public const RETENTION_DAYS = 14;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
        ];
    }

    /** @return Builder<self> */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
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
}

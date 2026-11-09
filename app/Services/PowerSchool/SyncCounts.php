<?php

namespace App\Services\PowerSchool;

use Illuminate\Database\Eloquent\Model;

/**
 * Tallies created / updated / unchanged / skipped rows per entity.
 */
final class SyncCounts
{
    /** @var array<string, array{created: int, updated: int, unchanged: int, skipped: int}> */
    private array $counts = [];

    public function record(string $entity, Model $model): void
    {
        $outcome = match (true) {
            $model->wasRecentlyCreated => 'created',
            $model->wasChanged() => 'updated',
            default => 'unchanged',
        };

        $this->bump($entity, $outcome);
    }

    public function skip(string $entity): void
    {
        $this->bump($entity, 'skipped');
    }

    public function merge(self $other): void
    {
        foreach ($other->counts as $entity => $c) {
            $this->bump($entity, 'created', $c['created']);
            $this->bump($entity, 'updated', $c['updated']);
            $this->bump($entity, 'unchanged', $c['unchanged']);
            $this->bump($entity, 'skipped', $c['skipped']);
        }
    }

    /**
     * @return array<string, array{created: int, updated: int, unchanged: int, skipped: int}>
     */
    public function toArray(): array
    {
        return $this->counts;
    }

    /**
     * @param  'created'|'updated'|'unchanged'|'skipped'  $outcome
     */
    private function bump(string $entity, string $outcome, int $by = 1): void
    {
        $this->counts[$entity] ??= ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0];
        $this->counts[$entity][$outcome] += $by;
    }
}

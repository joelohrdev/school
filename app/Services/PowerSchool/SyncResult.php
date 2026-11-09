<?php

namespace App\Services\PowerSchool;

use App\Models\SyncRun;

final readonly class SyncResult
{
    /**
     * @param  array<string, array{created: int, updated: int, unchanged: int, skipped: int}>  $counts
     * @param  list<string>  $errors
     */
    public function __construct(
        public string $status,
        public bool $dryRun,
        public array $counts = [],
        public array $errors = [],
        public ?SyncRun $run = null,
    ) {}

    public static function skipped(string $reason): self
    {
        return new self(SyncRun::STATUS_SKIPPED, false, errors: [$reason]);
    }

    public function succeeded(): bool
    {
        return $this->status === SyncRun::STATUS_SUCCEEDED;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\NotInSessionDay;
use App\Models\SyncRun;
use App\Services\Digest\DigestSender;
use App\Services\PowerSchool\SyncService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SyncPowerSchool extends Command
{
    protected $signature = 'powerschool:sync
        {--dry-run : Fetch and compare, then roll back every write}
        {--trigger=cli : What started this run (cli, schedule, manual)}
        {--digest : Email the digest afterwards if anything changed}';

    protected $description = "Pull the kids' grades, assignments and missing work from PowerSchool";

    public function handle(SyncService $sync, DigestSender $digest): int
    {
        $trigger = (string) $this->option('trigger');

        if (! in_array($trigger, [SyncRun::TRIGGER_CLI, SyncRun::TRIGGER_SCHEDULE, SyncRun::TRIGGER_MANUAL], true)) {
            $this->error("Unknown trigger [{$trigger}].");

            return self::INVALID;
        }

        $today = CarbonImmutable::now((string) config('powerschool.timezone'));

        if ($trigger === SyncRun::TRIGGER_SCHEDULE && NotInSessionDay::isDayOff($today)) {
            $this->info('No school today; skipping the scheduled sync.');

            return self::SUCCESS;
        }

        $result = $sync->run(dryRun: (bool) $this->option('dry-run'), trigger: $trigger);

        if ($result->counts !== []) {
            $this->table(
                ['Entity', 'Created', 'Updated', 'Unchanged', 'Skipped'],
                collect($result->counts)->map(fn (array $c, string $entity) => [$entity, ...array_values($c)])->values()->all(),
            );
        }

        foreach ($result->errors as $error) {
            $result->status === SyncRun::STATUS_SKIPPED ? $this->warn($error) : $this->error($error);
        }

        $label = $result->dryRun ? 'Dry run '.$result->status.' (nothing was saved).' : 'Sync '.$result->status.'.';
        $result->status === SyncRun::STATUS_FAILED ? $this->error($label) : $this->info($label);

        if ($this->option('digest') && ! $result->dryRun && $result->status !== SyncRun::STATUS_SKIPPED) {
            $this->line(match ($digest->send()) {
                DigestSender::SENT => 'Digest emailed.',
                DigestSender::NO_CHANGES => 'No changes; no digest sent.',
                default => 'MAIL_TO_PARENT is not set; no digest sent.',
            });
        }

        return $result->status === SyncRun::STATUS_FAILED ? self::FAILURE : self::SUCCESS;
    }
}

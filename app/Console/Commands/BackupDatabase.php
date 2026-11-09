<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--keep= : How many backups to keep on each disk}';

    protected $description = 'Write an encrypted copy of the SQLite database to the backup disks';

    public const PREFIX = 'kids-tracker-';

    public const SUFFIX = '.sqlite.gz.enc';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->error('backup:database only handles SQLite. Use your host\'s database backups for '.DB::connection()->getDriverName().'.');

            return self::FAILURE;
        }

        $disks = (array) config('backup.disks');
        $path = trim((string) config('backup.path'), '/');
        $keep = max(1, (int) ($this->option('keep') ?? config('backup.keep')));

        $contents = Crypt::encryptString((string) gzencode($this->snapshot(), 9));
        $name = self::PREFIX.now()->format('Y-m-d-His').self::SUFFIX;
        $failed = false;

        foreach ($disks as $disk) {
            try {
                Storage::disk($disk)->put("{$path}/{$name}", $contents);
                $pruned = $this->prune($disk, $path, $keep);
                $this->info("Saved {$name} to {$disk}".($pruned ? ", removed {$pruned} old" : '').'.');
            } catch (Throwable $e) {
                $failed = true;
                report($e);
                $this->error("Could not save to {$disk}: ".$e->getMessage());
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * A consistent copy of the live database, safe to take while a sync runs.
     */
    private function snapshot(): string
    {
        $tmp = storage_path('app/private/'.Str::random(16).'.sqlite');

        try {
            DB::statement('VACUUM INTO ?', [$tmp]);

            return (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    private function prune(string $disk, string $path, int $keep): int
    {
        $old = collect(Storage::disk($disk)->files($path))
            ->filter(fn (string $f) => str_starts_with(basename($f), self::PREFIX) && str_ends_with($f, self::SUFFIX))
            ->sort()
            ->reverse()
            ->slice($keep)
            ->values();

        if ($old->isNotEmpty()) {
            Storage::disk($disk)->delete($old->all());
        }

        return $old->count();
    }
}

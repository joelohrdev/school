<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--keep= : How many backups to keep on each disk}';

    protected $description = 'Write an encrypted copy of the database (SQLite or MySQL) to the backup disks';

    public const PREFIX = 'kids-tracker-';

    /** Every backup ends with this; the part before it says what's inside. */
    public const SUFFIX = '.gz.enc';

    public function handle(): int
    {
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['sqlite', 'mysql', 'mariadb'], true)) {
            $this->error("backup:database handles SQLite and MySQL, not {$driver}. Use your host's database backups.");

            return self::FAILURE;
        }

        try {
            [$dump, $kind] = $driver === 'sqlite' ? [$this->sqlite(), 'sqlite'] : [$this->mysql(), 'sql'];
        } catch (Throwable $e) {
            report($e);
            $this->error('Could not copy the database: '.$e->getMessage());

            return self::FAILURE;
        }

        $disks = (array) config('backup.disks');
        $path = trim((string) config('backup.path'), '/');
        $keep = max(1, (int) ($this->option('keep') ?? config('backup.keep')));

        $contents = Crypt::encryptString((string) gzencode($dump, 9));
        $name = self::PREFIX.now()->format('Y-m-d-His').".{$kind}".self::SUFFIX;
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
     * A consistent copy of the live SQLite file, safe to take while a sync runs.
     */
    private function sqlite(): string
    {
        $tmp = storage_path('app/private/'.Str::random(16).'.sqlite');

        try {
            DB::statement('VACUUM INTO ?', [$tmp]);

            return (string) file_get_contents($tmp);
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * A mysqldump in one transaction, so a sync running at the same time
     * can't leave it half-written. The password goes in the environment,
     * not the command line, so it never shows in the process list.
     */
    private function mysql(): string
    {
        $c = DB::connection()->getConfig();

        $command = array_values(array_filter([
            (string) config('backup.mysqldump', 'mysqldump'),
            '--single-transaction',
            '--quick',
            '--no-tablespaces',
            '--skip-lock-tables',
            ($c['unix_socket'] ?? '') !== '' ? '--socket='.$c['unix_socket'] : '--host='.($c['host'] ?? '127.0.0.1'),
            ($c['unix_socket'] ?? '') !== '' ? null : '--port='.($c['port'] ?? 3306),
            '--user='.($c['username'] ?? 'root'),
            (string) $c['database'],
        ]));

        $result = Process::env(['MYSQL_PWD' => (string) ($c['password'] ?? '')])
            ->timeout(600)
            ->run($command);

        if ($result->failed() || trim($result->output()) === '') {
            throw new RuntimeException('mysqldump failed: '.trim($result->errorOutput() ?: 'no output'));
        }

        return $result->output();
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

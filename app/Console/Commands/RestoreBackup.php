<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class RestoreBackup extends Command
{
    protected $signature = 'backup:decrypt {backup : Path to a .sqlite.gz.enc file} {output : Where to write the SQLite database}';

    protected $description = 'Decrypt a database backup into a plain SQLite file';

    public function handle(): int
    {
        $backup = (string) $this->argument('backup');
        $output = (string) $this->argument('output');

        if (! is_file($backup)) {
            $this->error("No file at {$backup}.");

            return self::FAILURE;
        }

        if (file_exists($output)) {
            $this->error("{$output} already exists. Pick a new path so nothing is overwritten.");

            return self::FAILURE;
        }

        try {
            $sqlite = gzdecode(Crypt::decryptString((string) file_get_contents($backup)));
        } catch (DecryptException) {
            $this->error('Could not decrypt it. Backups can only be opened with the APP_KEY that made them.');

            return self::FAILURE;
        }

        if ($sqlite === false) {
            $this->error('The backup decrypted but is not a valid compressed database.');

            return self::FAILURE;
        }

        file_put_contents($output, $sqlite);
        $this->info("Wrote {$output}. Point DB_DATABASE at it, or copy it over database/database.sqlite with the app stopped.");

        return self::SUCCESS;
    }
}

<?php

use App\Console\Commands\BackupDatabase;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

// VACUUM INTO can't run inside the transaction RefreshDatabase wraps each test in.
uses(DatabaseMigrations::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('offsite');
    config(['backup.disks' => ['local', 'offsite'], 'backup.path' => 'backups', 'backup.keep' => 14]);
});

function backups(string $disk): array
{
    return Storage::disk($disk)->files('backups');
}

it('writes an encrypted, compressed copy of the database to every disk', function () {
    User::factory()->create(['email' => 'backup-check@example.test']);

    $this->artisan('backup:database')->assertSuccessful();

    expect(backups('local'))->toHaveCount(1)
        ->and(backups('offsite'))->toHaveCount(1);

    $raw = Storage::disk('local')->get(backups('local')[0]);
    expect($raw)->not->toContain('backup-check@example.test');

    $sqlite = gzdecode(Crypt::decryptString($raw));
    expect($sqlite)->toStartWith('SQLite format 3')
        ->and($sqlite)->toContain('backup-check@example.test');
});

it('keeps only the newest backups', function () {
    foreach (range(1, 4) as $i) {
        $this->artisan('backup:database', ['--keep' => 2])->assertSuccessful();
        $this->travel(1)->minutes();
    }

    Storage::disk('local')->put('backups/unrelated.txt', 'leave me');

    expect(collect(backups('local'))->filter(fn ($f) => str_ends_with($f, BackupDatabase::SUFFIX)))->toHaveCount(2)
        ->and(backups('local'))->toContain('backups/unrelated.txt');
});

it('decrypts a backup into a usable SQLite file and refuses to overwrite', function () {
    $this->artisan('backup:database')->assertSuccessful();

    $dir = sys_get_temp_dir().'/kids-backup-test-'.uniqid();
    mkdir($dir);
    $in = "{$dir}/in.enc";
    $out = "{$dir}/out.sqlite";
    file_put_contents($in, Storage::disk('local')->get(backups('local')[0]));

    $this->artisan('backup:decrypt', ['backup' => $in, 'output' => $out])->assertSuccessful();
    expect(file_get_contents($out))->toStartWith('SQLite format 3');

    $this->artisan('backup:decrypt', ['backup' => $in, 'output' => $out])->assertFailed();

    array_map('unlink', glob("{$dir}/*"));
    rmdir($dir);
});

it('explains when a file cannot be decrypted', function () {
    $file = tempnam(sys_get_temp_dir(), 'bad');
    file_put_contents($file, 'not a backup');

    $this->artisan('backup:decrypt', ['backup' => $file, 'output' => $file.'.sqlite'])
        ->expectsOutputToContain('APP_KEY')
        ->assertFailed();

    unlink($file);
});

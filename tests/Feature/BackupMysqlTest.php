<?php

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

// No database trait: these point the default connection at a MySQL config
// that's never connected to, since the dump itself is faked.
beforeEach(function () {
    Storage::fake('local');

    config([
        'backup.disks' => ['local'],
        'backup.path' => 'backups',
        'database.default' => 'backup_mysql',
        'database.connections.backup_mysql' => [
            'driver' => 'mysql',
            'host' => 'db.internal',
            'port' => 3307,
            'database' => 'kids',
            'username' => 'forge',
            'password' => 'not-on-the-command-line',
            'unix_socket' => '',
        ],
    ]);
});

it('dumps MySQL in one transaction, with the password kept off the command line', function () {
    Process::fake(['*' => Process::result("-- MySQL dump\nCREATE TABLE `students` (id int);\n")]);

    $this->artisan('backup:database')->assertSuccessful();

    Process::assertRan(function (PendingProcess $process) {
        $command = (array) $process->command;

        return $command[0] === 'mysqldump'
            && in_array('--single-transaction', $command, true)
            && in_array('--host=db.internal', $command, true)
            && in_array('--port=3307', $command, true)
            && in_array('--user=forge', $command, true)
            && end($command) === 'kids'
            && ! str_contains(implode(' ', $command), 'not-on-the-command-line')
            && $process->environment['MYSQL_PWD'] === 'not-on-the-command-line';
    });

    $files = Storage::disk('local')->files('backups');
    expect($files)->toHaveCount(1)
        ->and($files[0])->toEndWith('.sql.gz.enc');

    expect(gzdecode(Crypt::decryptString(Storage::disk('local')->get($files[0]))))
        ->toContain('CREATE TABLE `students`');
});

it('fails without saving anything when mysqldump fails', function () {
    Process::fake(['*' => Process::result(errorOutput: 'Access denied', exitCode: 2)]);

    $this->artisan('backup:database')
        ->expectsOutputToContain('mysqldump failed: Access denied')
        ->assertFailed();

    expect(Storage::disk('local')->files('backups'))->toBe([]);
});

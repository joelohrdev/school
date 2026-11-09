<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Database backups
    |--------------------------------------------------------------------------
    |
    | PowerSchool only shows today's state, so this database is the only copy
    | of the grade history. `backup:database` writes a compressed, encrypted
    | copy (with APP_KEY) to each disk below and keeps the newest `keep`.
    |
    | Add an off-server disk from config/filesystems.php (s3 works with any
    | S3-compatible storage) so a lost server doesn't take the backups too.
    |
    */

    'disks' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('BACKUP_DISKS', 'local')),
    ))),

    'path' => env('BACKUP_PATH', 'backups'),

    'keep' => (int) env('BACKUP_KEEP', 14),

];

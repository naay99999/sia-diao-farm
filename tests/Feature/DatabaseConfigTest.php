<?php

use Illuminate\Support\Facades\DB;

test('file-backed sqlite runs in wal mode with a busy timeout so concurrent writes wait instead of failing', function () {
    $path = tempnam(sys_get_temp_dir(), 'farm-db-');

    config(['database.connections.sqlite_file' => [
        ...config('database.connections.sqlite'),
        'database' => $path,
    ]]);

    $connection = DB::connection('sqlite_file');

    expect($connection->selectOne('pragma journal_mode')->journal_mode)->toBe('wal')
        ->and((int) $connection->selectOne('pragma busy_timeout')->timeout)->toBe(5000)
        ->and((int) $connection->selectOne('pragma synchronous')->synchronous)->toBe(1);

    DB::purge('sqlite_file');
    @unlink($path);
    @unlink($path.'-wal');
    @unlink($path.'-shm');
});

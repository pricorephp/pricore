<?php

use Illuminate\Support\Facades\DB;

it('takes the sqlite write lock when a transaction begins', function () {
    $path = tempnam(sys_get_temp_dir(), 'pricore-sqlite-');

    config(['database.connections.sqlite_lock_test' => array_merge(
        config('database.connections.sqlite'),
        ['database' => $path],
    )]);

    $competingWriter = new PDO("sqlite:{$path}");
    $competingWriter->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $competingWriter->setAttribute(PDO::ATTR_TIMEOUT, 0);

    try {
        DB::connection('sqlite_lock_test')->transaction(function () use ($competingWriter) {
            expect(fn () => $competingWriter->exec('BEGIN IMMEDIATE'))
                ->toThrow(PDOException::class, 'database is locked');
        });
    } finally {
        DB::purge('sqlite_lock_test');
        unlink($path);
    }
});

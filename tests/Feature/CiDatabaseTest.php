<?php

use Illuminate\Support\Facades\DB;

// CI sets DB_CONNECTION=pgsql for the test step; if that override stops working, the suite
// silently falls back to phpunit.xml's SQLite and Postgres-only bugs go unnoticed
it('runs on postgres in CI', function () {
    expect(DB::getDriverName())->toBe('pgsql');
})->skip(! env('CI'), 'only in CI');

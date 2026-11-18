<?php

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabase as LaravelRefreshDatabase;

trait RefreshDatabase
{
    use LaravelRefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        if (!app()->environment('testing')
            || config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:'
            || config('database.connections.sqlite.url')) {
            throw new \LogicException('Las pruebas requieren SQLite en memoria y APP_ENV=testing.');
        }
    }

    protected function migrateDatabases(): void
    {
        $this->artisan('migrate', ['--database' => 'sqlite'])->assertExitCode(0);
    }
}

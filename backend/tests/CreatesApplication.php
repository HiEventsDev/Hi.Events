<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use RuntimeException;

trait CreatesApplication
{
    /**
     * Tracks whether application migrations have been applied to the test
     * database during this PHPUnit process. Migrations are expensive (a few
     * seconds) so we run them at most once per process; subsequent tests
     * inherit the migrated schema.
     */
    private static bool $migrationsApplied = false;

    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $this->guardAgainstNonTestDatabase($app);
        $this->ensureTestDatabaseIsMigrated($app);

        return $app;
    }

    /**
     * Hard safety net: any test that boots Laravel could (intentionally or not)
     * issue queries against the configured database. Refuse to run unless the
     * default connection's database name ends in "_test" so a misconfigured
     * environment can never touch a dev/staging/prod database.
     *
     * Runs as part of createApplication so the check fires before any trait
     * (DatabaseTransactions, RefreshDatabase, etc.) can open a connection.
     */
    private function guardAgainstNonTestDatabase(Application $app): void
    {
        $config = $app->make('config');
        $defaultConnection = $config->get('database.default');
        $database = $config->get("database.connections.{$defaultConnection}.database");

        if (! is_string($database) || ! str_ends_with($database, '_test')) {
            throw new RuntimeException(sprintf(
                'Refusing to run %s: default database connection "%s" points at "%s", '
                .'which does not end in "_test". Set DB_DATABASE to a *_test database '
                .'(CI uses hievents_test; locally configured via backend/.env.testing).',
                static::class,
                (string) $defaultConnection,
                (string) $database,
            ));
        }
    }

    /**
     * Apply application migrations to the test database exactly once per
     * PHPUnit process. Runs inside createApplication so it executes BEFORE
     * any DatabaseTransactions trait opens a wrapping transaction — some
     * migrations (e.g. CREATE INDEX CONCURRENTLY) refuse to run inside a
     * transaction block.
     *
     * Uses migrate:fresh so a leftover schema from a previous (possibly
     * crashed) run is wiped clean. Per-test data isolation remains the
     * responsibility of DatabaseTransactions / RefreshDatabase.
     */
    private function ensureTestDatabaseIsMigrated(Application $app): void
    {
        if (self::$migrationsApplied) {
            return;
        }

        $app->make(Kernel::class)->call('migrate:fresh', ['--force' => true]);

        self::$migrationsApplied = true;
    }
}

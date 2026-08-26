<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

trait CreatesApplication
{
    private static bool $migrationsApplied = false;

    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        $this->guardAgainstNonTestDatabase($app);

        if ($this->currentTestNeedsDatabase()) {
            $this->ensureTestDatabaseIsMigrated($app);
        }

        return $app;
    }

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

    private function ensureTestDatabaseIsMigrated(Application $app): void
    {
        if (self::$migrationsApplied) {
            return;
        }

        $app->make(Kernel::class)->call('migrate:fresh', ['--force' => true]);

        self::$migrationsApplied = true;
    }

    private function currentTestNeedsDatabase(): bool
    {
        $traits = class_uses_recursive(static::class);

        return isset($traits[DatabaseTransactions::class])
            || isset($traits[RefreshDatabase::class])
            || isset($traits[DatabaseMigrations::class]);
    }
}

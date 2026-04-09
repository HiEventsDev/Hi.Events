<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        $this->guardAgainstNonTestDatabase();
    }

    /**
     * Hard safety net: any test that boots Laravel could (intentionally or not)
     * issue queries against the configured database. Refuse to run unless the
     * default connection's database name ends in "_test" so a misconfigured
     * environment can never touch a dev/staging/prod database.
     *
     * The check reads config only — it does not open a connection — so tests
     * that never touch the database still pay only a constant-time cost and
     * never fail because the test database is unreachable.
     *
     * Marked final so individual tests cannot bypass it.
     */
    final protected function guardAgainstNonTestDatabase(): void
    {
        $defaultConnection = config('database.default');
        $database = config("database.connections.{$defaultConnection}.database");

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
}

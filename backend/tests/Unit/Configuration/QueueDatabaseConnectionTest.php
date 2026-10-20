<?php

namespace Tests\Unit\Configuration;

use Closure;
use Tests\TestCase;

class QueueDatabaseConnectionTest extends TestCase
{
    public function test_job_batches_and_failed_jobs_use_the_default_connection_when_db_connection_is_unset(): void
    {
        $this->withDbConnectionEnv(null, function (): void {
            $database = require base_path('config/database.php');
            $queue = require base_path('config/queue.php');

            $this->assertSame('pgsql', $database['default']);
            $this->assertSame($database['default'], $queue['batching']['database']);
            $this->assertSame($database['default'], $queue['failed']['database']);
        });
    }

    public function test_job_batches_and_failed_jobs_follow_db_connection_when_it_is_set(): void
    {
        $this->withDbConnectionEnv('sqlite', function (): void {
            $database = require base_path('config/database.php');
            $queue = require base_path('config/queue.php');

            $this->assertSame('sqlite', $database['default']);
            $this->assertSame('sqlite', $queue['batching']['database']);
            $this->assertSame('sqlite', $queue['failed']['database']);
        });
    }

    private function withDbConnectionEnv(?string $value, Closure $callback): void
    {
        $original = [
            'server' => $_SERVER['DB_CONNECTION'] ?? null,
            'env' => $_ENV['DB_CONNECTION'] ?? null,
            'putenv' => getenv('DB_CONNECTION'),
        ];

        $this->setDbConnectionEnv($value, $value, $value === null ? false : $value);

        try {
            $callback();
        } finally {
            $this->setDbConnectionEnv($original['server'], $original['env'], $original['putenv']);
        }
    }

    private function setDbConnectionEnv(?string $server, ?string $env, string|false $putenv): void
    {
        if ($server === null) {
            unset($_SERVER['DB_CONNECTION']);
        } else {
            $_SERVER['DB_CONNECTION'] = $server;
        }

        if ($env === null) {
            unset($_ENV['DB_CONNECTION']);
        } else {
            $_ENV['DB_CONNECTION'] = $env;
        }

        putenv($putenv === false ? 'DB_CONNECTION' : 'DB_CONNECTION='.$putenv);
    }
}

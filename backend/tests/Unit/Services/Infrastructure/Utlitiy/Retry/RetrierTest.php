<?php

namespace Tests\Unit\Services\Infrastructure\Utlitiy\Retry;

use Exception;
use HiEvents\Services\Infrastructure\Utlitiy\Retry\Retrier;
use PHPUnit\Framework\TestCase;

class RetrierTest extends TestCase
{
    public function test_retries_multiple_times_before_failing(): void
    {
        $attempts = 0;
        $maxAttempts = 3;

        $operation = function () use (&$attempts, $maxAttempts) {
            $attempts++;
            if ($attempts < $maxAttempts) {
                throw new Exception('Temporary failure');
            }

            return 'Success';
        };

        $retrier = new Retrier;
        $result = $retrier->retry($operation, $maxAttempts, 1);

        $this->assertEquals('Success', $result);
        $this->assertEquals($maxAttempts, $attempts);
    }

    public function test_fails_after_max_attempts(): void
    {
        $attempts = 0;
        $maxAttempts = 3;

        $operation = function () use (&$attempts) {
            $attempts++;
            throw new Exception('Persistent failure');
        };

        $retrier = new Retrier;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Persistent failure');

        try {
            $retrier->retry($operation, $maxAttempts, 1);
        } finally {
            $this->assertEquals($maxAttempts, $attempts);
        }
    }

    public function test_on_failure_callback_is_called(): void
    {
        $attempts = 0;
        $maxAttempts = 3;
        $onFailureCalled = false;

        $operation = function () use (&$attempts) {
            $attempts++;
            throw new Exception('Persistent failure');
        };

        $onFailure = function (int $attempt, Exception $e) use (&$onFailureCalled, $maxAttempts) {
            $onFailureCalled = true;
            $this->assertEquals($maxAttempts, $attempt);
            $this->assertEquals('Persistent failure', $e->getMessage());
        };

        $retrier = new Retrier;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Persistent failure');

        try {
            $retrier->retry($operation, $maxAttempts, 1, onFailure: $onFailure);
        } finally {
            $this->assertEquals($maxAttempts, $attempts);
            $this->assertTrue($onFailureCalled);
        }
    }
}

<?php

declare(strict_types=1);

namespace HiEvents\Services\Infrastructure\Utlitiy\Retry;

use Throwable;

class Retrier
{
    /**
     * @param callable(int $attempt):mixed $callableAction Receives 1-based attempt #
     * @param int $maxAttempts
     * @param int $baseDelayMs
     * @param int $maxDelayMs
     * @param null|callable(int $attempt, Throwable $e):void $onFailure Called before final throw
     * @param class-string<Throwable>|array<class-string<Throwable>> $retryOn Exceptions to retry
     * @return mixed
     * @throws Throwable
     */
    public function retry(
        callable  $callableAction,
        int       $maxAttempts = 3,
        int       $baseDelayMs = 25,
        int       $maxDelayMs = 250,
        ?callable $onFailure = null,
        array     $retryOn = [Throwable::class],
    ): mixed
    {
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $callableAction($attempt);
            } catch (Throwable $e) {
                $isRetryable = false;
                foreach ($retryOn as $cls) {
                    if ($e instanceof $cls) {
                        $isRetryable = true;
                        break;
                    }
                }

                $isLast = ($attempt === $maxAttempts);

                if (!$isRetryable || $isLast) {
                    if ($onFailure !== null) {
                        $onFailure($attempt, $e);
                    }
                    throw $e;
                }

                // Exponential backoff with cap
                $delay = min($maxDelayMs, $baseDelayMs * (1 << max(0, $attempt - 1)));
                usleep($delay * 1000);
            }
        }

        // Unreachable
        return null;
    }
}

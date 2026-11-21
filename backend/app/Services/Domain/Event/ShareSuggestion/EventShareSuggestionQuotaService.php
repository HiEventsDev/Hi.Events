<?php

namespace HiEvents\Services\Domain\Event\ShareSuggestion;

use Illuminate\Cache\RateLimiter;

class EventShareSuggestionQuotaService
{
    public const int DAILY_LIMIT = 20;

    private const int WINDOW_SECONDS = 86400;

    public function __construct(
        private readonly RateLimiter $rateLimiter,
    ) {}

    public function remaining(int $accountId): int
    {
        return max(0, $this->rateLimiter->remaining($this->key($accountId), self::DAILY_LIMIT));
    }

    public function recordGeneration(int $accountId): void
    {
        $this->rateLimiter->hit($this->key($accountId), self::WINDOW_SECONDS);
    }

    private function key(int $accountId): string
    {
        return 'event-share-suggestions:account:'.$accountId;
    }
}

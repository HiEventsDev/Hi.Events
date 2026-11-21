<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Domain\Event\ShareSuggestion;

use HiEvents\Services\Domain\Event\ShareSuggestion\EventShareSuggestionQuotaService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\Repository;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventShareSuggestionQuotaServiceTest extends TestCase
{
    private EventShareSuggestionQuotaService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new EventShareSuggestionQuotaService(new RateLimiter(new Repository(new ArrayStore)));
    }

    public function test_an_account_starts_with_its_full_daily_allowance(): void
    {
        $this->assertSame(EventShareSuggestionQuotaService::DAILY_LIMIT, $this->service->remaining(1));
    }

    public function test_each_generation_uses_one_of_the_accounts_allowance_and_nobody_elses(): void
    {
        $this->service->recordGeneration(1);
        $this->service->recordGeneration(1);

        $this->assertSame(EventShareSuggestionQuotaService::DAILY_LIMIT - 2, $this->service->remaining(1));
        $this->assertSame(EventShareSuggestionQuotaService::DAILY_LIMIT, $this->service->remaining(2));
    }

    public function test_the_allowance_stops_at_zero_and_returns_after_a_day(): void
    {
        Carbon::setTestNow('2026-11-20 12:00:00');

        for ($generation = 0; $generation < EventShareSuggestionQuotaService::DAILY_LIMIT + 3; $generation++) {
            $this->service->recordGeneration(1);
        }

        $this->assertSame(0, $this->service->remaining(1));

        Carbon::setTestNow('2026-11-21 12:00:01');

        $this->assertSame(EventShareSuggestionQuotaService::DAILY_LIMIT, $this->service->remaining(1));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}

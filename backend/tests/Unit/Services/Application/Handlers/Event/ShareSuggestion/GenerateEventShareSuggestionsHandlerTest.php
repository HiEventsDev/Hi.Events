<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Application\Handlers\Event\ShareSuggestion;

use HiEvents\DomainObjects\Enums\ShareCaptionTone;
use HiEvents\DomainObjects\Enums\ShareSuggestionAvailability;
use HiEvents\Exceptions\ShareSuggestionGenerationFailedException;
use HiEvents\Exceptions\ShareSuggestionQuotaExceededException;
use HiEvents\Exceptions\ShareSuggestionsUnavailableException;
use HiEvents\Services\Application\Handlers\Event\ShareSuggestion\GenerateEventShareSuggestionsHandler;
use HiEvents\Services\Domain\Event\ShareSuggestion\DTO\ShareCaptionDTO;
use HiEvents\Services\Domain\Event\ShareSuggestion\EventShareCaptionService;
use HiEvents\Services\Domain\Event\ShareSuggestion\EventShareSuggestionAvailabilityService;
use HiEvents\Services\Domain\Event\ShareSuggestion\EventShareSuggestionQuotaService;
use HiEvents\Services\Domain\Event\ShareSuggestion\EventShareSuggestionStore;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\RateLimiter;
use Illuminate\Cache\Repository;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class GenerateEventShareSuggestionsHandlerTest extends TestCase
{
    private const int EVENT_ID = 10;

    private const int ACCOUNT_ID = 20;

    private EventShareSuggestionAvailabilityService&MockInterface $availabilityService;

    private EventShareCaptionService&MockInterface $captionService;

    private EventShareSuggestionQuotaService $quotaService;

    private EventShareSuggestionStore $store;

    private GenerateEventShareSuggestionsHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $cache = new Repository(new ArrayStore);

        $this->availabilityService = Mockery::mock(EventShareSuggestionAvailabilityService::class);
        $this->captionService = Mockery::mock(EventShareCaptionService::class);
        $this->quotaService = new EventShareSuggestionQuotaService(new RateLimiter($cache));
        $this->store = new EventShareSuggestionStore($cache);

        $this->handler = new GenerateEventShareSuggestionsHandler(
            $this->availabilityService,
            $this->quotaService,
            $this->captionService,
            $this->store,
        );
    }

    public function test_generated_captions_are_kept_for_the_event_and_use_one_of_the_accounts_allowance(): void
    {
        $captions = [new ShareCaptionDTO(ShareCaptionTone::FRIENDLY, 'Come along on Friday')];
        $this->availabilityService->allows('resolve')->andReturn(ShareSuggestionAvailability::AVAILABLE);
        $this->captionService->expects('generate')->with(self::EVENT_ID)->andReturn($captions);

        $result = $this->handler->handle(self::EVENT_ID, self::ACCOUNT_ID);

        $this->assertSame($captions, $result->captions);
        $this->assertSame(EventShareSuggestionQuotaService::DAILY_LIMIT - 1, $result->remaining_generations);
        $this->assertEquals($captions, $this->store->latest(self::EVENT_ID));
    }

    public function test_an_ineligible_account_never_reaches_the_model(): void
    {
        $this->availabilityService->allows('resolve')->andReturn(ShareSuggestionAvailability::PAYMENTS_NOT_CONNECTED);
        $this->captionService->expects('generate')->never();

        $this->expectException(ShareSuggestionsUnavailableException::class);

        $this->handler->handle(self::EVENT_ID, self::ACCOUNT_ID);
    }

    public function test_an_account_that_has_used_its_allowance_never_reaches_the_model(): void
    {
        $this->availabilityService->allows('resolve')->andReturn(ShareSuggestionAvailability::AVAILABLE);
        $this->captionService->expects('generate')->never();

        for ($generation = 0; $generation < EventShareSuggestionQuotaService::DAILY_LIMIT; $generation++) {
            $this->quotaService->recordGeneration(self::ACCOUNT_ID);
        }

        $this->expectException(ShareSuggestionQuotaExceededException::class);

        $this->handler->handle(self::EVENT_ID, self::ACCOUNT_ID);
    }

    public function test_a_failed_generation_does_not_use_the_allowance_or_replace_earlier_captions(): void
    {
        $earlier = [new ShareCaptionDTO(ShareCaptionTone::SHORT, 'Jazz night')];
        $this->store->replace(self::EVENT_ID, $earlier);
        $this->availabilityService->allows('resolve')->andReturn(ShareSuggestionAvailability::AVAILABLE);
        $this->captionService->allows('generate')->andThrow(new ShareSuggestionGenerationFailedException('Provider overloaded'));

        try {
            $this->handler->handle(self::EVENT_ID, self::ACCOUNT_ID);
            $this->fail('Expected the generation to fail');
        } catch (ShareSuggestionGenerationFailedException) {
            $this->assertSame(EventShareSuggestionQuotaService::DAILY_LIMIT, $this->quotaService->remaining(self::ACCOUNT_ID));
            $this->assertEquals($earlier, $this->store->latest(self::EVENT_ID));
        }
    }
}

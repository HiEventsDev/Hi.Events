<?php

namespace HiEvents\Services\Application\Handlers\Event\ShareSuggestion;

use HiEvents\DomainObjects\Enums\ShareSuggestionAvailability;
use HiEvents\Exceptions\ShareSuggestionGenerationFailedException;
use HiEvents\Exceptions\ShareSuggestionQuotaExceededException;
use HiEvents\Exceptions\ShareSuggestionsUnavailableException;
use HiEvents\Services\Domain\Event\ShareSuggestion\DTO\EventShareSuggestionsDTO;
use HiEvents\Services\Domain\Event\ShareSuggestion\EventShareCaptionService;
use HiEvents\Services\Domain\Event\ShareSuggestion\EventShareSuggestionAvailabilityService;
use HiEvents\Services\Domain\Event\ShareSuggestion\EventShareSuggestionQuotaService;
use HiEvents\Services\Domain\Event\ShareSuggestion\EventShareSuggestionStore;

class GenerateEventShareSuggestionsHandler
{
    public function __construct(
        private readonly EventShareSuggestionAvailabilityService $availabilityService,
        private readonly EventShareSuggestionQuotaService $quotaService,
        private readonly EventShareCaptionService $captionService,
        private readonly EventShareSuggestionStore $store,
    ) {}

    /**
     * @throws ShareSuggestionsUnavailableException
     * @throws ShareSuggestionQuotaExceededException
     * @throws ShareSuggestionGenerationFailedException
     */
    public function handle(int $eventId, int $accountId): EventShareSuggestionsDTO
    {
        if ($this->availabilityService->resolve($eventId, $accountId) !== ShareSuggestionAvailability::AVAILABLE) {
            throw new ShareSuggestionsUnavailableException;
        }

        if ($this->quotaService->remaining($accountId) === 0) {
            throw new ShareSuggestionQuotaExceededException;
        }

        $captions = $this->captionService->generate($eventId);

        $this->quotaService->recordGeneration($accountId);
        $this->store->replace($eventId, $captions);

        return new EventShareSuggestionsDTO(
            availability: ShareSuggestionAvailability::AVAILABLE,
            captions: $captions,
            remaining_generations: $this->quotaService->remaining($accountId),
        );
    }
}

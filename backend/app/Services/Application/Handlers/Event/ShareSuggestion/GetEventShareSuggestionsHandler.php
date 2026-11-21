<?php

namespace HiEvents\Services\Application\Handlers\Event\ShareSuggestion;

use HiEvents\DomainObjects\Enums\ShareSuggestionAvailability;
use HiEvents\Services\Domain\Event\ShareSuggestion\DTO\EventShareSuggestionsDTO;
use HiEvents\Services\Domain\Event\ShareSuggestion\EventShareSuggestionAvailabilityService;
use HiEvents\Services\Domain\Event\ShareSuggestion\EventShareSuggestionQuotaService;
use HiEvents\Services\Domain\Event\ShareSuggestion\EventShareSuggestionStore;

class GetEventShareSuggestionsHandler
{
    public function __construct(
        private readonly EventShareSuggestionAvailabilityService $availabilityService,
        private readonly EventShareSuggestionQuotaService $quotaService,
        private readonly EventShareSuggestionStore $store,
    ) {}

    public function handle(int $eventId, int $accountId): EventShareSuggestionsDTO
    {
        $availability = $this->availabilityService->resolve($eventId, $accountId);

        return new EventShareSuggestionsDTO(
            availability: $availability,
            captions: $availability === ShareSuggestionAvailability::AVAILABLE ? $this->store->latest($eventId) : [],
            remaining_generations: $this->quotaService->remaining($accountId),
        );
    }
}

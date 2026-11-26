<?php

namespace HiEvents\Services\Domain\Event\ShareSuggestion;

use HiEvents\DomainObjects\Enums\ShareSuggestionAvailability;
use HiEvents\DomainObjects\OrganizerStripePlatformDomainObject;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use Illuminate\Config\Repository;

class EventShareSuggestionAvailabilityService
{
    public function __construct(
        private readonly Repository $config,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
    ) {}

    public function resolve(int $eventId, int $accountId): ShareSuggestionAvailability
    {
        if (! $this->config->get('ai.providers.anthropic.key')) {
            return ShareSuggestionAvailability::NOT_CONFIGURED;
        }

        $account = $this->accountRepository->findById($accountId);

        if ($account->getAccountVerifiedAt() === null) {
            return ShareSuggestionAvailability::EMAIL_NOT_CONFIRMED;
        }

        $event = $this->eventRepository->findById($eventId);

        if ($event->getStatus() === EventStatus::PENDING_MANUAL_REVIEW->name) {
            return ShareSuggestionAvailability::EVENT_UNDER_REVIEW;
        }

        if (! $this->config->get('app.saas_mode_enabled') || $account->getIsManuallyVerified()) {
            return ShareSuggestionAvailability::AVAILABLE;
        }

        $organizer = $this->organizerRepository
            ->loadRelation(OrganizerStripePlatformDomainObject::class)
            ->findById($event->getOrganizerId());

        return $organizer->isStripeSetupComplete()
            ? ShareSuggestionAvailability::AVAILABLE
            : ShareSuggestionAvailability::PAYMENTS_NOT_CONNECTED;
    }
}

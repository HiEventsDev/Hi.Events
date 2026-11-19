<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Insight;

use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmInsightsRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Insight\DTO\EventAudienceDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;
use HiEvents\Enterprise\Crm\Services\Domain\Insight\ContactSummaryMapper;
use HiEvents\Exceptions\ResourceNotFoundException;

class GetEventAudienceHandler
{
    private const TOP_CONTACTS = 5;

    private const OVERLAPS = 5;

    private const CACHE_SECONDS = 300;

    public function __construct(
        private readonly CrmInsightsRepositoryInterface $insightsRepository,
        private readonly ContactSummaryMapper $contactSummaryMapper,
        private readonly CrmCacheVersion $cacheVersion,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $organizerId, int $eventId): EventAudienceDTO
    {
        if (! $this->insightsRepository->eventBelongsToOrganizer($organizerId, $eventId)) {
            throw new ResourceNotFoundException(__('Event not found'));
        }

        return new EventAudienceDTO(...$this->cacheVersion->remember(
            $organizerId,
            'event-audience:'.$eventId,
            self::CACHE_SECONDS,
            fn (): array => $this->computeAudience($organizerId, $eventId),
        ));
    }

    private function computeAudience(int $organizerId, int $eventId): array
    {
        $audience = $this->insightsRepository->getEventAudience($organizerId, $eventId);
        $turnout = $this->insightsRepository->getExpectedTurnout($organizerId, $eventId, now());
        $contacts = (int) $audience->contacts_count;

        return [
            'event_id' => $eventId,
            'contacts_count' => $contacts,
            'tickets_count' => (int) $audience->tickets_count,
            'mailable_count' => (int) $audience->mailable_count,
            'checked_in_count' => (int) $audience->checked_in_count,
            'new_count' => $contacts - (int) $audience->returning_count,
            'returning_count' => (int) $audience->returning_count,
            'baseline_show_rate' => $turnout->baseline_show_rate === null ? null : round((float) $turnout->baseline_show_rate * 100, 1),
            'expected_tickets' => $turnout->expected_tickets === null ? null : (int) round((float) $turnout->expected_tickets),
            'likely_no_show_contacts' => (int) $turnout->likely_no_show_contacts,
            'top_contacts' => $this->contactSummaryMapper->map($this->insightsRepository->getEventTopContacts($organizerId, $eventId, self::TOP_CONTACTS)),
            'overlaps' => $this->insightsRepository->getEventOverlaps($organizerId, $eventId, self::OVERLAPS)->map(static fn (object $row) => [
                'event_id' => (int) $row->event_id,
                'title' => $row->title,
                'shared_contacts' => (int) $row->shared_contacts,
            ])->all(),
        ];
    }
}

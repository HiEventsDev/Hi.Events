<?php

declare(strict_types=1);

namespace HiEvents\Services\Handlers\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\OrganizerNotFoundException;
use HiEvents\Services\Domain\Event\CreateEventService;
use HiEvents\Services\Domain\Organizer\OrganizerFetchService;
use HiEvents\Services\Handlers\Event\DTO\CreateEventDTO;
use Throwable;

class CreateEventHandler
{
    public function __construct(
        private readonly CreateEventService    $createEventService,
        private readonly OrganizerFetchService $organizerFetchService
    )
    {
    }

    /**
     * @throws OrganizerNotFoundException
     * @throws Throwable
     */
    public function handle(CreateEventDTO $eventData): EventDomainObject
    {
        $organizer = $this->organizerFetchService->fetchOrganizer(
            organizerId: $eventData->organizer_id,
            accountId: $eventData->account_id
        );

        $event = (new EventDomainObject())
            ->setOrganizerId($eventData->organizer_id)
            ->setAccountId($eventData->account_id)
            ->setUserId($eventData->user_id)
            ->setTitle($eventData->title)
            ->setStartDate($eventData->start_date)
            ->setEndDate($eventData->end_date)
            ->setDescription($eventData->description)
            ->setAttributes($eventData->attributes?->toArray())
            ->setTimezone($eventData->timezone ?? $organizer->getTimezone())
            ->setCurrency($eventData->currency ?? $organizer->getCurrency())
            ->setStatus($eventData->status)
            ->setEventSettings($eventData->event_settings)
            ->setLocationDetails($eventData->location_details?->toArray());

        return $this->createEventService->createEvent($event);
    }
}

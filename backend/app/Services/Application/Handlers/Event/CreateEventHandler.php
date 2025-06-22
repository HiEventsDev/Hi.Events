<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Event;

use HiEvents\DomainObjects\Enums\EventCategory;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Exceptions\OrganizerNotFoundException;
use HiEvents\Services\Application\Handlers\Event\DTO\CreateEventDTO;
use HiEvents\Services\Domain\Event\CreateEventService;
use HiEvents\Services\Domain\Organizer\OrganizerFetchService;
use HiEvents\Services\Domain\ProductCategory\CreateProductCategoryService;
use Illuminate\Database\DatabaseManager;
use Throwable;

class CreateEventHandler
{
    public function __construct(
        private readonly CreateEventService           $createEventService,
        private readonly OrganizerFetchService        $organizerFetchService,
        private readonly CreateProductCategoryService $createProductCategoryService,
        private readonly DatabaseManager              $databaseManager,
    )
    {
    }

    /**
     * @throws OrganizerNotFoundException
     * @throws Throwable
     */
    public function handle(CreateEventDTO $eventData): EventDomainObject
    {
        return $this->databaseManager->transaction(fn() => $this->createEvent($eventData));
    }

    /**
     * @throws OrganizerNotFoundException
     * @throws Throwable
     */
    private function createEvent(CreateEventDTO $eventData): EventDomainObject
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
            ->setCategory($eventData->category?->value ?? EventCategory::OTHER->value)
            ->setStatus($eventData->status)
            ->setEventSettings($eventData->event_settings)
            ->setLocationDetails($eventData->location_details?->toArray());

        $newEvent = $this->createEventService->createEvent($event);

        $this->createProductCategoryService->createDefaultProductCategory($newEvent);

        return $newEvent;
    }
}

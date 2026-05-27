<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Event;

use HiEvents\DomainObjects\Enums\EventCategory;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\Exceptions\OrganizerNotFoundException;
use HiEvents\Jobs\Event\Webhook\DispatchEventWebhookJob;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\CreateEventDTO;
use HiEvents\Services\Domain\Event\CreateEventService;
use HiEvents\Services\Domain\EventLocation\EventLocationUpserter;
use HiEvents\Services\Domain\Organizer\OrganizerFetchService;
use HiEvents\Services\Domain\ProductCategory\CreateProductCategoryService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use Illuminate\Database\DatabaseManager;
use Throwable;

class CreateEventHandler
{
    public function __construct(
        private readonly CreateEventService $createEventService,
        private readonly OrganizerFetchService $organizerFetchService,
        private readonly CreateProductCategoryService $createProductCategoryService,
        private readonly EventLocationUpserter $eventLocationUpserter,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws OrganizerNotFoundException
     * @throws Throwable
     */
    public function handle(CreateEventDTO $eventData): EventDomainObject
    {
        return $this->databaseManager->transaction(fn () => $this->createEvent($eventData));
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

        $event = (new EventDomainObject)
            ->setOrganizerId($eventData->organizer_id)
            ->setAccountId($eventData->account_id)
            ->setUserId($eventData->user_id)
            ->setTitle($eventData->title)
            ->setDescription($eventData->description)
            ->setAttributes($eventData->attributes?->toArray())
            ->setTimezone($eventData->timezone ?? $organizer->getTimezone())
            ->setCurrency($eventData->currency ?? $organizer->getCurrency())
            ->setCategory($eventData->category?->value ?? EventCategory::OTHER->value)
            ->setStatus($eventData->status)
            ->setType($eventData->type?->name)
            ->setEventSettings($eventData->event_settings);

        $newEvent = $this->createEventService->createEvent(
            eventData: $event,
            startDate: $eventData->start_date,
            endDate: $eventData->end_date,
        );

        if ($eventData->event_location !== null) {
            $eventLocation = $this->eventLocationUpserter->createForEvent(
                eventId: $newEvent->getId(),
                accountId: $eventData->account_id,
                data: $eventData->event_location,
            );

            $this->eventRepository->updateWhere(
                attributes: [
                    EventDomainObjectAbstract::EVENT_LOCATION_ID => $eventLocation->getId(),
                ],
                where: [
                    'id' => $newEvent->getId(),
                ],
            );

            $newEvent->setEventLocationId($eventLocation->getId());
            $newEvent->setEventLocation($eventLocation);
        }

        $this->createProductCategoryService->createDefaultProductCategory($newEvent);

        DispatchEventWebhookJob::dispatch(
            $newEvent->getId(),
            DomainEventType::EVENT_CREATED,
        );

        return $newEvent;
    }
}

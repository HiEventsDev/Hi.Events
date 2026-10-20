<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\EventLocation;

use HiEvents\DomainObjects\Enums\LocationType;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\Generated\EventLocationDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\EventLocationRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\Location\LocationOwnershipValidator;
use HiEvents\Services\Infrastructure\HtmlPurifier\HtmlPurifierService;

class EventLocationUpserter
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventLocationRepositoryInterface $eventLocationRepository,
        private readonly LocationOwnershipValidator $locationOwnershipValidator,
        private readonly HtmlPurifierService $purifier,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function createForEvent(int $eventId, int $accountId, EventLocationData $data): EventLocationDomainObject
    {
        $this->assertOwnership($eventId, $accountId, $data->location_id);

        return $this->eventLocationRepository->create([
            EventLocationDomainObjectAbstract::EVENT_ID => $eventId,
            EventLocationDomainObjectAbstract::SHORT_ID => IdHelper::shortId(IdHelper::EVENT_LOCATION_PREFIX),
            EventLocationDomainObjectAbstract::TYPE => $data->type->name,
            EventLocationDomainObjectAbstract::LOCATION_ID => $data->type === LocationType::IN_PERSON ? $data->location_id : null,
            EventLocationDomainObjectAbstract::ONLINE_EVENT_CONNECTION_DETAILS => $this->resolveConnectionDetails($data),
        ]);
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function updateInPlace(int $eventLocationId, int $eventId, int $accountId, EventLocationData $data): EventLocationDomainObject
    {
        $this->assertOwnership($eventId, $accountId, $data->location_id);

        $existing = $this->eventLocationRepository->findFirstWhere([
            EventLocationDomainObjectAbstract::ID => $eventLocationId,
            EventLocationDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($existing === null) {
            throw new ResourceNotFoundException(
                __('Event location :id not found for event :event', ['id' => $eventLocationId, 'event' => $eventId]),
            );
        }

        return $this->eventLocationRepository->updateFromArray($eventLocationId, [
            EventLocationDomainObjectAbstract::TYPE => $data->type->name,
            EventLocationDomainObjectAbstract::LOCATION_ID => $data->type === LocationType::IN_PERSON ? $data->location_id : null,
            EventLocationDomainObjectAbstract::ONLINE_EVENT_CONNECTION_DETAILS => $this->resolveConnectionDetails($data),
        ]);
    }

    /**
     * @throws ResourceNotFoundException
     */
    private function assertOwnership(int $eventId, int $accountId, ?int $locationId): void
    {
        $event = $this->eventRepository->findFirstWhere([
            'id' => $eventId,
            'account_id' => $accountId,
        ]);

        if ($event === null) {
            throw new ResourceNotFoundException(__('Event :id not found', ['id' => $eventId]));
        }

        $this->locationOwnershipValidator->assertOwnedBy($locationId, $event->getOrganizerId(), $accountId);
    }

    private function resolveConnectionDetails(EventLocationData $data): ?string
    {
        if ($data->type !== LocationType::ONLINE) {
            return null;
        }

        if ($data->online_event_connection_details === null) {
            return null;
        }

        return $this->purifier->purify($data->online_event_connection_details);
    }
}

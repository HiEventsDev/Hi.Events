<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Lineup;

use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\EventSpaceDomainObject;
use HiEvents\DomainObjects\Generated\EventLocationDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventSpaceDomainObjectAbstract;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventLocationRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSpaceRepositoryInterface;
use HiEvents\Services\Application\Handlers\Lineup\DTO\UpsertEventSpaceDTO;
use HiEvents\Services\Domain\Event\EventSpamCheckDispatchService;

class UpsertEventSpaceHandler
{
    public function __construct(
        private readonly EventSpaceRepositoryInterface $spaceRepository,
        private readonly EventLocationRepositoryInterface $eventLocationRepository,
        private readonly EventSpamCheckDispatchService $spamCheckDispatchService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(UpsertEventSpaceDTO $dto): EventSpaceDomainObject
    {
        if ($dto->event_location_id !== null) {
            $this->assertVenueBelongsToEvent($dto->event_id, $dto->event_location_id);
        }

        $attributes = [
            EventSpaceDomainObjectAbstract::NAME => trim($dto->name),
            EventSpaceDomainObjectAbstract::EVENT_LOCATION_ID => $dto->event_location_id,
        ];

        if ($dto->space_id === null) {
            $spaceId = $this->spaceRepository->create([
                ...$attributes,
                EventSpaceDomainObjectAbstract::EVENT_ID => $dto->event_id,
            ])->getId();
        } else {
            $space = $this->spaceRepository->findFirstWhere([
                EventSpaceDomainObjectAbstract::ID => $dto->space_id,
                EventSpaceDomainObjectAbstract::EVENT_ID => $dto->event_id,
            ]);

            if ($space === null) {
                throw new ResourceNotFoundException(__('Stage or room not found'));
            }

            $spaceId = $space->getId();
            $this->spaceRepository->updateFromArray($spaceId, $attributes);
        }

        $this->spamCheckDispatchService->dispatchForEvent($dto->event_id);

        return $this->spaceRepository
            ->loadRelation(new Relationship(EventLocationDomainObject::class, nested: [
                new Relationship(LocationDomainObject::class, name: 'location'),
            ], name: 'event_location'))
            ->findById($spaceId);
    }

    /**
     * @throws ResourceNotFoundException
     */
    private function assertVenueBelongsToEvent(int $eventId, int $eventLocationId): void
    {
        $venue = $this->eventLocationRepository->findFirstWhere([
            EventLocationDomainObjectAbstract::ID => $eventLocationId,
            EventLocationDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($venue === null) {
            throw new ResourceNotFoundException(__('Venue not found'));
        }
    }
}

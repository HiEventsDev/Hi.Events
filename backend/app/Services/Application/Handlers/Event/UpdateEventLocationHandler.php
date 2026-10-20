<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Event;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\Event\DTO\UpdateEventLocationDTO;
use HiEvents\Services\Domain\EventLocation\EventLocationCleaner;
use HiEvents\Services\Domain\EventLocation\EventLocationUpserter;
use Illuminate\Database\DatabaseManager;
use Throwable;

class UpdateEventLocationHandler
{
    public function __construct(
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventLocationUpserter $eventLocationUpserter,
        private readonly EventLocationCleaner $eventLocationCleaner,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(UpdateEventLocationDTO $dto): EventDomainObject
    {
        return $this->databaseManager->transaction(function () use ($dto) {
            $event = $this->eventRepository->findFirstWhere([
                'id' => $dto->event_id,
                'account_id' => $dto->account_id,
            ]);

            if ($event === null) {
                throw new ResourceNotFoundException(__('Event :id not found', ['id' => $dto->event_id]));
            }

            $previousEventLocationId = $event->getEventLocationId();

            if ($dto->event_location !== null) {
                if ($previousEventLocationId === null) {
                    $created = $this->eventLocationUpserter->createForEvent(
                        eventId: $dto->event_id,
                        accountId: $dto->account_id,
                        data: $dto->event_location,
                    );
                    $this->eventRepository->updateWhere(
                        attributes: [EventDomainObjectAbstract::EVENT_LOCATION_ID => $created->getId()],
                        where: ['id' => $dto->event_id, 'account_id' => $dto->account_id],
                    );
                } else {
                    $this->eventLocationUpserter->updateInPlace(
                        eventLocationId: $previousEventLocationId,
                        eventId: $dto->event_id,
                        accountId: $dto->account_id,
                        data: $dto->event_location,
                    );
                }
            } elseif ($dto->clear_event_location && $previousEventLocationId !== null) {
                $this->eventRepository->updateWhere(
                    attributes: [EventDomainObjectAbstract::EVENT_LOCATION_ID => null],
                    where: ['id' => $dto->event_id, 'account_id' => $dto->account_id],
                );
                $this->eventLocationCleaner->deleteIfOrphaned($previousEventLocationId);
            }

            return $this->eventRepository
                ->loadRelation(new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location', nested: [
                    new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
                ]))
                ->loadRelation(new Relationship(domainObject: EventOccurrenceDomainObject::class, nested: [
                    new Relationship(domainObject: EventLocationDomainObject::class, name: 'event_location', nested: [
                        new Relationship(domainObject: LocationDomainObject::class, name: 'location'),
                    ]),
                ]))
                ->findFirstWhere([
                    'id' => $dto->event_id,
                    'account_id' => $dto->account_id,
                ]);
        });
    }
}

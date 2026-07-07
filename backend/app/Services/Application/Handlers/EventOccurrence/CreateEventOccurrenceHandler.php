<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\UpsertEventOccurrenceDTO;
use HiEvents\Services\Domain\EventLocation\EventLocationUpserter;
use Illuminate\Database\DatabaseManager;
use Throwable;

class CreateEventOccurrenceHandler
{
    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventLocationUpserter $eventLocationUpserter,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws Throwable
     */
    public function handle(UpsertEventOccurrenceDTO $dto): EventOccurrenceDomainObject
    {
        return $this->databaseManager->transaction(function () use ($dto) {
            $eventLocationId = null;

            if ($dto->event_location !== null) {
                $event = $this->eventRepository->findById($dto->event_id);
                if ($event === null) {
                    throw new ResourceNotFoundException(__('Event :id not found', ['id' => $dto->event_id]));
                }

                $eventLocation = $this->eventLocationUpserter->createForEvent(
                    eventId: $dto->event_id,
                    accountId: $event->getAccountId(),
                    data: $dto->event_location,
                );
                $eventLocationId = $eventLocation->getId();
            }

            return $this->occurrenceRepository->create([
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $dto->event_id,
                EventOccurrenceDomainObjectAbstract::SHORT_ID => IdHelper::shortId(IdHelper::OCCURRENCE_PREFIX),
                EventOccurrenceDomainObjectAbstract::START_DATE => $dto->start_date,
                EventOccurrenceDomainObjectAbstract::END_DATE => $dto->end_date,
                EventOccurrenceDomainObjectAbstract::STATUS => EventOccurrenceStatus::ACTIVE->name,
                EventOccurrenceDomainObjectAbstract::CAPACITY => $dto->capacity,
                EventOccurrenceDomainObjectAbstract::USED_CAPACITY => 0,
                EventOccurrenceDomainObjectAbstract::LABEL => $dto->label,
                EventOccurrenceDomainObjectAbstract::SHOW_AVAILABLE_CAPACITY => $dto->show_available_capacity,
                EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN => $dto->is_overridden,
                EventOccurrenceDomainObjectAbstract::EVENT_LOCATION_ID => $eventLocationId,
            ]);
        });
    }
}

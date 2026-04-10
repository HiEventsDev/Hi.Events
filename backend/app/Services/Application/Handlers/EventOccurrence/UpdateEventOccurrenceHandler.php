<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\UpsertEventOccurrenceDTO;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Throwable;

class UpdateEventOccurrenceHandler
{
    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
        private readonly DatabaseManager                    $databaseManager,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handle(int $occurrenceId, UpsertEventOccurrenceDTO $dto): EventOccurrenceDomainObject
    {
        return $this->databaseManager->transaction(function () use ($occurrenceId, $dto) {
            $occurrence = $this->occurrenceRepository->findFirstWhere([
                EventOccurrenceDomainObjectAbstract::ID => $occurrenceId,
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $dto->event_id,
            ]);

            if (!$occurrence) {
                throw new ResourceNotFoundException(
                    __('Occurrence :id not found for event :eventId', [
                        'id' => $occurrenceId,
                        'eventId' => $dto->event_id,
                    ])
                );
            }

            return $this->occurrenceRepository->updateFromArray(
                id: $occurrence->getId(),
                attributes: [
                    EventOccurrenceDomainObjectAbstract::START_DATE => $dto->start_date,
                    EventOccurrenceDomainObjectAbstract::END_DATE => $dto->end_date,
                    EventOccurrenceDomainObjectAbstract::STATUS => $dto->status ?? $occurrence->getStatus(),
                    EventOccurrenceDomainObjectAbstract::CAPACITY => $dto->capacity,
                    EventOccurrenceDomainObjectAbstract::LABEL => $dto->label,
                    EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN => true,
                ]
            );
        });
    }
}

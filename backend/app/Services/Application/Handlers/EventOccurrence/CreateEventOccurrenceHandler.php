<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\EventOccurrence;

use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Helper\IdHelper;
use HiEvents\Services\Application\Handlers\EventOccurrence\DTO\UpsertEventOccurrenceDTO;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Throwable;

class CreateEventOccurrenceHandler
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
    public function handle(UpsertEventOccurrenceDTO $dto): EventOccurrenceDomainObject
    {
        return $this->databaseManager->transaction(function () use ($dto) {
            return $this->occurrenceRepository->create([
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $dto->event_id,
                EventOccurrenceDomainObjectAbstract::SHORT_ID => IdHelper::shortId(IdHelper::OCCURRENCE_PREFIX),
                EventOccurrenceDomainObjectAbstract::START_DATE => $dto->start_date,
                EventOccurrenceDomainObjectAbstract::END_DATE => $dto->end_date,
                EventOccurrenceDomainObjectAbstract::STATUS => $dto->status ?? EventOccurrenceStatus::ACTIVE->name,
                EventOccurrenceDomainObjectAbstract::CAPACITY => $dto->capacity,
                EventOccurrenceDomainObjectAbstract::USED_CAPACITY => 0,
                EventOccurrenceDomainObjectAbstract::LABEL => $dto->label,
                EventOccurrenceDomainObjectAbstract::IS_OVERRIDDEN => $dto->is_overridden,
            ]);
        });
    }
}

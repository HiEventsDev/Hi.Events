<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Event;

use HiEvents\DomainObjects\EventStatisticDomainObject;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;
use HiEvents\Services\Domain\Event\DTO\EventCountsResponseDTO;

readonly class EventCountsFetchService
{
    public function __construct(
        private EventStatisticRepositoryInterface $eventStatisticRepository,
    ) {}

    public function getEventCounts(int $eventId): EventCountsResponseDTO
    {
        $statistics = $this->eventStatisticRepository->findFirstWhere([
            EventStatisticDomainObject::EVENT_ID => $eventId,
        ]);

        return new EventCountsResponseDTO(
            total_orders: $statistics?->getOrdersCreated() ?? 0,
            total_attendees_registered: $statistics?->getAttendeesRegistered() ?? 0,
        );
    }
}

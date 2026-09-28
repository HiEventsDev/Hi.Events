<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Cashless;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\Generated\EventOccurrenceDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\Repository\Eloquent\Value\OrderAndDirection;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;

class CashlessOccurrenceResolver
{
    public function __construct(
        private readonly EventOccurrenceRepositoryInterface $occurrenceRepository,
    ) {}

    public function resolveForSale(int $eventId): ?int
    {
        $occurrences = $this->occurrenceRepository->findWhere(
            where: [
                EventOccurrenceDomainObjectAbstract::EVENT_ID => $eventId,
                EventOccurrenceDomainObjectAbstract::STATUS => EventOccurrenceStatus::ACTIVE->name,
            ],
            orderAndDirections: [
                new OrderAndDirection(EventOccurrenceDomainObjectAbstract::START_DATE, OrderAndDirection::DIRECTION_ASC),
            ],
        );

        $now = Carbon::now();

        $current = $occurrences->first(
            fn (EventOccurrenceDomainObject $occurrence) => Carbon::parse($occurrence->getStartDate())->lte($now)
                && ($occurrence->getEndDate() === null || Carbon::parse($occurrence->getEndDate())->gte($now)),
        );

        $upcoming = $occurrences->first(
            fn (EventOccurrenceDomainObject $occurrence) => Carbon::parse($occurrence->getStartDate())->gt($now),
        );

        return ($current ?? $upcoming ?? $occurrences->last())?->getId();
    }
}

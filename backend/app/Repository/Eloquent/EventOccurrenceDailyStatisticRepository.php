<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\EventOccurrenceDailyStatisticDomainObject;
use HiEvents\Models\EventOccurrenceDailyStatistic;
use HiEvents\Repository\Interfaces\EventOccurrenceDailyStatisticRepositoryInterface;

/**
 * @extends BaseRepository<EventOccurrenceDailyStatisticDomainObject>
 */
class EventOccurrenceDailyStatisticRepository extends BaseRepository implements EventOccurrenceDailyStatisticRepositoryInterface
{
    protected function getModel(): string
    {
        return EventOccurrenceDailyStatistic::class;
    }

    public function getDomainObject(): string
    {
        return EventOccurrenceDailyStatisticDomainObject::class;
    }
}

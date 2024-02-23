<?php

namespace TicketKitten\Repository\Eloquent;

use TicketKitten\DomainObjects\EventDailyStatisticDomainObject;
use TicketKitten\Models\EventDailyStatistic;
use TicketKitten\Repository\Interfaces\EventDailyStatisticRepositoryInterface;

class EventDailyStatisticRepository extends BaseRepository implements EventDailyStatisticRepositoryInterface
{
    protected function getModel(): string
    {
        return EventDailyStatistic::class;
    }

    public function getDomainObject(): string
    {
        return EventDailyStatisticDomainObject::class;
    }
}

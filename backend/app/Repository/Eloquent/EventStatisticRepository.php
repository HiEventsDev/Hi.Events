<?php

namespace TicketKitten\Repository\Eloquent;

use TicketKitten\DomainObjects\EventStatisticDomainObject;
use TicketKitten\Models\EventStatistic;
use TicketKitten\Repository\Interfaces\EventStatisticRepositoryInterface;

class EventStatisticRepository extends BaseRepository implements EventStatisticRepositoryInterface
{
    protected function getModel(): string
    {
        return EventStatistic::class;
    }

    public function getDomainObject(): string
    {
        return EventStatisticDomainObject::class;
    }
}

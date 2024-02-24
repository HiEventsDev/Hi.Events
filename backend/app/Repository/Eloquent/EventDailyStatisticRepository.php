<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\EventDailyStatisticDomainObject;
use HiEvents\Models\EventDailyStatistic;
use HiEvents\Repository\Interfaces\EventDailyStatisticRepositoryInterface;

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

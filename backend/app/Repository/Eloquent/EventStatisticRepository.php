<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\EventStatisticDomainObject;
use HiEvents\Models\EventStatistic;
use HiEvents\Repository\Interfaces\EventStatisticRepositoryInterface;

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

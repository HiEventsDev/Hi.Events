<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\EventOccurrenceStatisticDomainObject;
use HiEvents\Models\EventOccurrenceStatistic;
use HiEvents\Repository\Interfaces\EventOccurrenceStatisticRepositoryInterface;

/**
 * @extends BaseRepository<EventOccurrenceStatisticDomainObject>
 */
class EventOccurrenceStatisticRepository extends BaseRepository implements EventOccurrenceStatisticRepositoryInterface
{
    protected function getModel(): string
    {
        return EventOccurrenceStatistic::class;
    }

    public function getDomainObject(): string
    {
        return EventOccurrenceStatisticDomainObject::class;
    }
}

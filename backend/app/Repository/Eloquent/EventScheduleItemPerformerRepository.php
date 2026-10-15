<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\EventScheduleItemPerformerDomainObject;
use HiEvents\Models\EventScheduleItemPerformer;
use HiEvents\Repository\Interfaces\EventScheduleItemPerformerRepositoryInterface;

/**
 * @extends BaseRepository<EventScheduleItemPerformerDomainObject>
 */
class EventScheduleItemPerformerRepository extends BaseRepository implements EventScheduleItemPerformerRepositoryInterface
{
    protected function getModel(): string
    {
        return EventScheduleItemPerformer::class;
    }

    public function getDomainObject(): string
    {
        return EventScheduleItemPerformerDomainObject::class;
    }
}

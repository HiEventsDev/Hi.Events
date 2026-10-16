<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\EventSpaceDomainObject;
use HiEvents\Models\EventSpace;
use HiEvents\Repository\Interfaces\EventSpaceRepositoryInterface;

/**
 * @extends BaseRepository<EventSpaceDomainObject>
 */
class EventSpaceRepository extends BaseRepository implements EventSpaceRepositoryInterface
{
    protected function getModel(): string
    {
        return EventSpace::class;
    }

    public function getDomainObject(): string
    {
        return EventSpaceDomainObject::class;
    }
}

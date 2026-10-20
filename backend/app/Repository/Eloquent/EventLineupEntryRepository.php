<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\EventLineupEntryDomainObject;
use HiEvents\Models\EventLineupEntry;
use HiEvents\Repository\Interfaces\EventLineupEntryRepositoryInterface;

/**
 * @extends BaseRepository<EventLineupEntryDomainObject>
 */
class EventLineupEntryRepository extends BaseRepository implements EventLineupEntryRepositoryInterface
{
    protected function getModel(): string
    {
        return EventLineupEntry::class;
    }

    public function getDomainObject(): string
    {
        return EventLineupEntryDomainObject::class;
    }
}

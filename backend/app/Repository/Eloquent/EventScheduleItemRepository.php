<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\EventScheduleItemDomainObject;
use HiEvents\Models\EventScheduleItem;
use HiEvents\Repository\Interfaces\EventScheduleItemRepositoryInterface;

/**
 * @extends BaseRepository<EventScheduleItemDomainObject>
 */
class EventScheduleItemRepository extends BaseRepository implements EventScheduleItemRepositoryInterface
{
    protected function getModel(): string
    {
        return EventScheduleItem::class;
    }

    public function getDomainObject(): string
    {
        return EventScheduleItemDomainObject::class;
    }

    public function deleteUntitledWithoutPerformers(array $eventIds): int
    {
        return $this->runQuery(fn () => $this->model
            ->newQuery()
            ->whereIn('event_id', $eventIds)
            ->whereNull('title')
            ->whereDoesntHave('performers')
            ->delete());
    }
}

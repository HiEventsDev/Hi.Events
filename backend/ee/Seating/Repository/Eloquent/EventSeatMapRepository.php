<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Repository\Eloquent;

use HiEvents\DomainObjects\EventSeatMapDomainObject;
use HiEvents\Enterprise\Licensing\Repository\UsageScope;
use HiEvents\Enterprise\Seating\Repository\Interfaces\EventSeatMapRepositoryInterface;
use HiEvents\Models\EventSeatMap;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<EventSeatMapDomainObject>
 */
class EventSeatMapRepository extends BaseRepository implements EventSeatMapRepositoryInterface
{
    protected function getModel(): string
    {
        return EventSeatMap::class;
    }

    public function getDomainObject(): string
    {
        return EventSeatMapDomainObject::class;
    }

    public function findEventIdsWithSeatMaps(array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        return $this->runQuery(fn () => DB::table('event_seat_maps')
            ->whereIn('event_id', $eventIds)
            ->pluck('event_id')
            ->all());
    }

    public function existsForUpcomingEvent(?int $accountId): bool
    {
        return $this->runQuery(fn () => UsageScope::upcomingEvent(
            DB::table('event_seat_maps')->join('events', 'events.id', '=', 'event_seat_maps.event_id'),
            $accountId,
        )->exists());
    }
}

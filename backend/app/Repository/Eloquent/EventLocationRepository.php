<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\Models\EventLocation;
use HiEvents\Repository\Interfaces\EventLocationRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<EventLocationDomainObject>
 */
class EventLocationRepository extends BaseRepository implements EventLocationRepositoryInterface
{
    protected function getModel(): string
    {
        return EventLocation::class;
    }

    public function getDomainObject(): string
    {
        return EventLocationDomainObject::class;
    }

    public function isReferenced(int $eventLocationId): bool
    {
        $eventCount = DB::table('events')
            ->where('event_location_id', $eventLocationId)
            ->whereNull('deleted_at')
            ->count();

        if ($eventCount > 0) {
            return true;
        }

        $occurrenceCount = DB::table('event_occurrences')
            ->where('event_location_id', $eventLocationId)
            ->whereNull('deleted_at')
            ->count();

        return $occurrenceCount > 0;
    }
}

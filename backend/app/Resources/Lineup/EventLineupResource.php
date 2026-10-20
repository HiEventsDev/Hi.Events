<?php

declare(strict_types=1);

namespace HiEvents\Resources\Lineup;

use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\Resources\BaseResource;
use HiEvents\Resources\Location\LocationPublicResource;
use HiEvents\Services\Application\Handlers\Lineup\DTO\EventLineupDTO;
use Illuminate\Http\Request;

/**
 * @mixin EventLineupDTO
 */
class EventLineupResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'entries' => LineupEntryResource::collection($this->entries),
            'spaces' => EventSpaceResource::collection($this->spaces),
            'schedule_items' => ScheduleItemResource::collection($this->schedule_items),
            'venues' => ($this->venues ?? collect())
                ->map(fn (EventLocationDomainObject $venue) => [
                    'id' => $venue->getId(),
                    'location' => new LocationPublicResource($venue->getLocation()),
                ])
                ->values()
                ->all(),
        ];
    }
}

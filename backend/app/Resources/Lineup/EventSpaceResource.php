<?php

declare(strict_types=1);

namespace HiEvents\Resources\Lineup;

use HiEvents\DomainObjects\EventSpaceDomainObject;
use HiEvents\Resources\BaseResource;
use HiEvents\Resources\Location\LocationPublicResource;
use Illuminate\Http\Request;

/**
 * @mixin EventSpaceDomainObject
 */
class EventSpaceResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $eventLocation = $this->getEventLocation();
        $location = $eventLocation?->getLocation();

        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'event_location_id' => $eventLocation?->getId(),
            'venue' => $location === null ? null : new LocationPublicResource($location),
        ];
    }
}

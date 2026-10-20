<?php

declare(strict_types=1);

namespace HiEvents\Resources\EventLocation;

use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\Resources\BaseResource;
use HiEvents\Resources\Location\LocationResource;
use Illuminate\Http\Request;

/**
 * @mixin EventLocationDomainObject
 */
class EventLocationResource extends BaseResource
{
    private readonly bool $includeOnlineConnectionDetails;

    public function __construct(
        mixed $resource,
        mixed $includeOnlineConnectionDetails = true,
    ) {
        $this->includeOnlineConnectionDetails = is_bool($includeOnlineConnectionDetails)
            ? $includeOnlineConnectionDetails
            : true;

        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'type' => $this->getType(),
            'location_id' => $this->getLocationId(),
            'online_event_connection_details' => $this->when(
                condition: $this->includeOnlineConnectionDetails,
                value: fn () => $this->getOnlineEventConnectionDetails(),
            ),
            'location' => $this->when(
                condition: $this->getLocation() !== null,
                value: fn () => new LocationResource($this->getLocation()),
            ),
        ];
    }
}

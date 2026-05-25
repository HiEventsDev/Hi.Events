<?php

declare(strict_types=1);

namespace HiEvents\Resources\EventLocation;

use HiEvents\DomainObjects\EventLocationDomainObject;
use HiEvents\Resources\BaseResource;
use HiEvents\Resources\Location\LocationPublicResource;
use Illuminate\Http\Request;

/**
 * @mixin EventLocationDomainObject
 *
 * Public-surface projection of an EventLocation. Mirrors EventLocationResource
 * but embeds LocationPublicResource (no internal IDs / provider metadata)
 * and gates online_event_connection_details behind the post-checkout flag.
 */
class EventLocationResourcePublic extends BaseResource
{
    private readonly bool $includeOnlineConnectionDetails;

    public function __construct(
        mixed $resource,
        mixed $includeOnlineConnectionDetails = false,
    ) {
        $this->includeOnlineConnectionDetails = is_bool($includeOnlineConnectionDetails)
            ? $includeOnlineConnectionDetails
            : false;

        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        return [
            'type' => $this->getType(),
            'online_event_connection_details' => $this->when(
                condition: $this->includeOnlineConnectionDetails,
                value: fn () => $this->getOnlineEventConnectionDetails(),
            ),
            'location' => $this->when(
                condition: $this->getLocation() !== null,
                value: fn () => new LocationPublicResource($this->getLocation()),
            ),
        ];
    }
}

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
class EventSpaceResourcePublic extends BaseResource
{
    public function toArray(Request $request): array
    {
        $location = $this->getEventLocation()?->getLocation();

        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'venue' => $location === null ? null : new LocationPublicResource($location),
        ];
    }
}

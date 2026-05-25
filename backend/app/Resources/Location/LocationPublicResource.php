<?php

declare(strict_types=1);

namespace HiEvents\Resources\Location;

use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin LocationDomainObject
 *
 * Public-surface projection of a Location: only the fields a buyer needs
 * to render a venue. Internal IDs, organizer scoping, provider names,
 * place IDs and timestamps are intentionally omitted.
 */
class LocationPublicResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->getName(),
            'structured_address' => $this->getStructuredAddress(),
            'latitude' => $this->getLatitude(),
            'longitude' => $this->getLongitude(),
        ];
    }
}

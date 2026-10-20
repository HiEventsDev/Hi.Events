<?php

declare(strict_types=1);

namespace HiEvents\Resources\Location;

use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin LocationDomainObject
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

<?php

declare(strict_types=1);

namespace HiEvents\Resources\Location;

use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\Resources\BaseResource;
use Illuminate\Http\Request;

/**
 * @mixin LocationDomainObject
 */
class LocationResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'organizer_id' => $this->getOrganizerId(),
            'name' => $this->getName(),
            'structured_address' => $this->getStructuredAddress(),
            'latitude' => $this->getLatitude(),
            'longitude' => $this->getLongitude(),
            'provider' => $this->getProvider(),
            'provider_place_id' => $this->getProviderPlaceId(),
            'created_at' => $this->getCreatedAt(),
            'updated_at' => $this->getUpdatedAt(),
        ];
    }
}

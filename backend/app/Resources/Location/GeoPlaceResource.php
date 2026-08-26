<?php

namespace HiEvents\Resources\Location;

use HiEvents\Resources\BaseResource;
use HiEvents\Services\Infrastructure\Geo\DTO\GeoPlaceDTO;

/**
 * @mixin GeoPlaceDTO
 */
class GeoPlaceResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'provider' => $this->provider,
            'provider_place_id' => $this->provider_place_id,
            'address' => $this->address->toArray(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'display_name' => $this->display_name,
        ];
    }
}

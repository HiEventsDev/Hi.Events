<?php

namespace HiEvents\Resources\Location;

use HiEvents\Resources\BaseResource;
use HiEvents\Services\Infrastructure\Geo\DTO\GeoSuggestionDTO;

/**
 * @mixin GeoSuggestionDTO
 */
class GeoSuggestionResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'provider_place_id' => $this->provider_place_id,
            'primary_text' => $this->primary_text,
            'secondary_text' => $this->secondary_text,
        ];
    }
}

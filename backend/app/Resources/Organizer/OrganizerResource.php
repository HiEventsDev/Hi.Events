<?php

namespace HiEvents\Resources\Organizer;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Resources\Image\ImageResource;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrganizerDomainObject
 */
class OrganizerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'email' => $this->getEmail(),
            'phone' => $this->getPhone(),
            'website' => $this->getWebsite(),
            'description' => $this->getDescription(),
            'timezone' => $this->getTimezone(),
            'currency' => $this->getCurrency(),
            'images' => $this->when(
                (bool)$this->getImages(),
                fn() => ImageResource::collection($this->getImages())
            ),
        ];
    }
}

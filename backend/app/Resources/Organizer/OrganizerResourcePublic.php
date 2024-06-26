<?php

namespace HiEvents\Resources\Organizer;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Resources\Image\ImageResource;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OrganizerDomainObject
 */
class OrganizerResourcePublic extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'website' => $this->getWebsite(),
            'description' => $this->getDescription(),
            'images' => $this->when(
                (bool)$this->getImages(),
                fn() => ImageResource::collection($this->getImages())
            ),
        ];
    }
}

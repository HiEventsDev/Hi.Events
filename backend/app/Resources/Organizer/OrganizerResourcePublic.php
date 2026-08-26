<?php

namespace HiEvents\Resources\Organizer;

use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Resources\Event\EventResourcePublic;
use HiEvents\Resources\Image\ImageResource;
use HiEvents\Resources\Location\LocationPublicResource;
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
            'slug' => $this->getSlug(),
            'status' => $this->getStatus(),
            'location' => $this->when(
                condition: $this->getLocationRecord() !== null,
                value: fn () => new LocationPublicResource($this->getLocationRecord()),
            ),
            'images' => $this->when(
                (bool) $this->getImages(),
                fn () => ImageResource::collection($this->getImages())
            ),
            'events' => $this->when(
                condition: ! is_null($this->getEvents()),
                value: fn () => EventResourcePublic::collection($this->getEvents())
            ),
            'settings' => $this->when(
                condition: ! is_null($this->getOrganizerSettings()),
                value: fn () => new OrganizerSettingsPublicResource($this->getOrganizerSettings())
            ),
        ];
    }
}

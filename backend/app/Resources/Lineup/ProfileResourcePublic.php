<?php

declare(strict_types=1);

namespace HiEvents\Resources\Lineup;

use HiEvents\DomainObjects\ProfileDomainObject;
use HiEvents\Resources\BaseResource;
use HiEvents\Resources\Image\ImageResource;
use Illuminate\Http\Request;

/**
 * @mixin ProfileDomainObject
 */
class ProfileResourcePublic extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'tagline' => $this->getTagline(),
            'bio' => $this->getBio(),
            'links' => $this->getLinks() ?? [],
            'images' => ImageResource::collection($this->getImages() ?? collect()),
        ];
    }
}

<?php

namespace TicketKitten\Resources\Image;

use TicketKitten\DomainObjects\ImageDomainObject;
use TicketKitten\Helper\Url;
use TicketKitten\Resources\BaseResource;

/**
 * @mixin ImageDomainObject
 */
class ImageResource extends BaseResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'url' => Url::getCdnUrl($this->getPath()),
            'size' => $this->getSize(),
            'file_name' => $this->getFileName(),
            'mime_type' => $this->getMimeType(),
            'type' => $this->getType()
        ];
    }
}

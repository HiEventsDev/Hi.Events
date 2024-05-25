<?php

namespace HiEvents\Resources\Image;

use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\Helper\Url;
use HiEvents\Resources\BaseResource;

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

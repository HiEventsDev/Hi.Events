<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\Models\Image;
use HiEvents\Repository\Interfaces\ImageRepositoryInterface;

class ImageRepository extends BaseRepository implements ImageRepositoryInterface
{
    protected function getModel(): string
    {
        return Image::class;
    }

    public function getDomainObject(): string
    {
        return ImageDomainObject::class;
    }
}

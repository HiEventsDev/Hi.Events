<?php

namespace TicketKitten\Repository\Eloquent;

use TicketKitten\DomainObjects\ImageDomainObject;
use TicketKitten\Models\Image;
use TicketKitten\Repository\Interfaces\ImageRepositoryInterface;

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

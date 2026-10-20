<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\ProductOccurrenceVisibilityDomainObject;
use HiEvents\Models\ProductOccurrenceVisibility;
use HiEvents\Repository\Interfaces\ProductOccurrenceVisibilityRepositoryInterface;

class ProductOccurrenceVisibilityRepository extends BaseRepository implements ProductOccurrenceVisibilityRepositoryInterface
{
    protected function getModel(): string
    {
        return ProductOccurrenceVisibility::class;
    }

    public function getDomainObject(): string
    {
        return ProductOccurrenceVisibilityDomainObject::class;
    }
}

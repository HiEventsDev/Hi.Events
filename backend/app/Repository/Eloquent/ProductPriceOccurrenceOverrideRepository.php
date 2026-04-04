<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\ProductPriceOccurrenceOverrideDomainObject;
use HiEvents\Models\ProductPriceOccurrenceOverride;
use HiEvents\Repository\Interfaces\ProductPriceOccurrenceOverrideRepositoryInterface;

class ProductPriceOccurrenceOverrideRepository extends BaseRepository implements ProductPriceOccurrenceOverrideRepositoryInterface
{
    protected function getModel(): string
    {
        return ProductPriceOccurrenceOverride::class;
    }

    public function getDomainObject(): string
    {
        return ProductPriceOccurrenceOverrideDomainObject::class;
    }
}

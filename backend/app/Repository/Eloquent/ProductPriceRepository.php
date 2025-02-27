<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Models\ProductPrice;
use HiEvents\Repository\Interfaces\ProductPriceRepositoryInterface;

class ProductPriceRepository extends BaseRepository implements ProductPriceRepositoryInterface
{
    protected function getModel(): string
    {
        return ProductPrice::class;
    }

    public function getDomainObject(): string
    {
        return ProductPriceDomainObject::class;
    }
}

<?php

namespace HiEvents\DomainObjects;

use Illuminate\Support\Collection;

class ProductCategoryDomainObject extends Generated\ProductCategoryDomainObjectAbstract
{
    public ?Collection $products = null;

    public function setProducts(Collection $products): void
    {
        $this->products = $products;
    }

    public function getProducts(): ?Collection
    {
        return $this->products;
    }
}

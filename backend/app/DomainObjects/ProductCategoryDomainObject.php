<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Interfaces\IsSortable;
use HiEvents\DomainObjects\SortingAndFiltering\AllowedSorts;
use Illuminate\Support\Collection;

class ProductCategoryDomainObject extends Generated\ProductCategoryDomainObjectAbstract implements IsSortable
{
    public static function getDefaultSort(): string
    {
        return self::ORDER;
    }

    public static function getDefaultSortDirection(): string
    {
        return 'asc';
    }

    public static function getAllowedSorts(): AllowedSorts
    {
        return new AllowedSorts([
            self::ORDER => [
                'asc' => __('Order Ascending'),
                'desc' => __('Order Descending'),
            ],
            self::CREATED_AT => [
                'asc' => __('Oldest First'),
                'desc' => __('Newest First'),
            ],
            self::NAME => [
                'asc' => __('Name A-Z'),
                'desc' => __('Name Z-A'),
            ],
        ]);
    }

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

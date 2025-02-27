<?php

namespace HiEvents\Services\Domain\Product;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\Helper\DateHelper;
use HiEvents\Repository\Eloquent\ProductPriceRepository;
use Illuminate\Support\Collection;

class ProductPriceCreateService
{
    public function __construct(
        private readonly ProductPriceRepository $productPriceRepository,
    )
    {
    }

    public function createPrices(
        int               $productId,
        Collection        $prices,
        EventDomainObject $event,
    ): Collection
    {
        return (new Collection($prices->map(fn(ProductPriceDomainObject $price, int $index) => $this->productPriceRepository->create([
            'product_id' => $productId,
            'price' => $price->getPrice(),
            'label' => $price->getLabel(),
            'sale_start_date' => $price->getSaleStartDate()
                ? DateHelper::convertToUTC($price->getSaleStartDate(), $event->getTimezone())
                : null,
            'sale_end_date' => $price->getSaleEndDate()
                ? DateHelper::convertToUTC($price->getSaleEndDate(), $event->getTimezone())
                : null,
            'initial_quantity_available' => $price->getInitialQuantityAvailable(),
            'is_hidden' => $price->getIsHidden(),
            'order' => $index + 1,
        ]))));
    }
}

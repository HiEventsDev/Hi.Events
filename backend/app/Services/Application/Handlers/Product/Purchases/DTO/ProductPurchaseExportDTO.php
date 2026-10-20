<?php

namespace HiEvents\Services\Application\Handlers\Product\Purchases\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\Repository\DTO\ProductPurchase\ProductPurchaseDTO;
use Illuminate\Support\LazyCollection;

class ProductPurchaseExportDTO extends BaseDataObject
{
    /**
     * @param  LazyCollection<int, ProductPurchaseDTO>  $purchases
     */
    public function __construct(
        public LazyCollection $purchases,
        public string $timezone,
    ) {}
}

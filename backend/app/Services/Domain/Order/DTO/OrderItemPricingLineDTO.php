<?php

namespace HiEvents\Services\Domain\Order\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\Services\Domain\Product\DTO\OrderProductPriceDTO;
use HiEvents\Services\Domain\Product\DTO\PriceDTO;

class OrderItemPricingLineDTO extends BaseDataObject
{
    public function __construct(
        public ProductDomainObject $product,
        public OrderProductPriceDTO $product_price,
        public PriceDTO $prices,
        public ?int $event_occurrence_id,
    ) {}
}

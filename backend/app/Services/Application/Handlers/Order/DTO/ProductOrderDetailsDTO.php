<?php

namespace HiEvents\Services\Application\Handlers\Order\DTO;

use HiEvents\DataTransferObjects\Attributes\CollectionOf;
use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\Services\Domain\Product\DTO\OrderProductPriceDTO;
use Illuminate\Support\Collection;

class ProductOrderDetailsDTO extends BaseDTO
{
    public function __construct(
        public readonly int $product_id,
        #[CollectionOf(OrderProductPriceDTO::class)]
        public Collection   $quantities,
    )
    {
    }
}

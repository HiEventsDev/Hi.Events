<?php

namespace HiEvents\Services\Domain\Product\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class OrderProductPriceDTO extends BaseDTO
{
    public function __construct(
        public readonly int    $quantity,
        public readonly int    $price_id,
        public readonly ?float $price = null // used for donation products
    )
    {
    }
}

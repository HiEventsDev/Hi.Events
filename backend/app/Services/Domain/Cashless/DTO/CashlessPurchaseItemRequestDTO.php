<?php

namespace HiEvents\Services\Domain\Cashless\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class CashlessPurchaseItemRequestDTO extends BaseDataObject
{
    public function __construct(
        public int $product_id,
        public int $product_price_id,
        public int $quantity,
    ) {}
}

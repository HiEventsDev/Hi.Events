<?php

namespace HiEvents\Services\Domain\Cashless\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class CashlessTransactionItemDTO extends BaseDataObject
{
    public function __construct(
        public int $product_id,
        public int $product_price_id,
        public string $product_title,
        public float $unit_price,
        public int $quantity,
        public float $total,
    ) {}
}

<?php

namespace HiEvents\Services\Domain\Order\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class OrderLineDiscountAllocationDTO extends BaseDataObject
{
    public function __construct(
        public float $per_unit_discount,
        public int $quantity,
    ) {}
}

<?php

namespace HiEvents\Repository\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SalesByCurrencyDTO extends BaseDataObject
{
    public function __construct(
        public string $currency,
        public float $amount,
    ) {}
}

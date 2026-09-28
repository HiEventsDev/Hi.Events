<?php

namespace HiEvents\Services\Domain\Cashless\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class CashlessQuoteDTO extends BaseDataObject
{
    public function __construct(
        public float $subtotal,
        public float $fees,
        public float $taxes,
        public float $total,
    ) {}
}

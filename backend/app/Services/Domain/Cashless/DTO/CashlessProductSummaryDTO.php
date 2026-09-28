<?php

namespace HiEvents\Services\Domain\Cashless\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class CashlessProductSummaryDTO extends BaseDataObject
{
    public function __construct(
        public string $title,
        public int $quantity,
        public float $total,
    ) {}
}

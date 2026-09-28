<?php

namespace HiEvents\Services\Domain\Cashless\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class CashlessSalesPointSummaryDTO extends BaseDataObject
{
    public function __construct(
        public string $name,
        public float $spent,
        public int $purchases_count,
        public float $topped_up,
    ) {}
}

<?php

namespace HiEvents\Services\Domain\Tax\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class TaxCalculationResponse extends BaseDTO
{
    public function __construct(
        public readonly float $feeTotal,
        public readonly float $taxTotal,
        public readonly array $rollUp,
    )
    {
    }
}

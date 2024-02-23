<?php

namespace TicketKitten\Service\Common\Tax\DTO;

use TicketKitten\DataTransferObjects\BaseDTO;

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

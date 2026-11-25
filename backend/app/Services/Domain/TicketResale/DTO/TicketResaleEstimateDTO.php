<?php

namespace HiEvents\Services\Domain\TicketResale\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class TicketResaleEstimateDTO extends BaseDataObject
{
    /**
     * @param  string[]  $add_ons
     */
    public function __construct(
        public readonly string $currency,
        public readonly int $amount_paid,
        public readonly int $estimated_fees,
        public readonly int $estimated_refund,
        public readonly array $add_ons,
    ) {}
}

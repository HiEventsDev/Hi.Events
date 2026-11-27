<?php

namespace HiEvents\Services\Domain\TicketResale\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class ResaleRefundBreakdownDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $basis,
        public readonly int $cost_share,
        public readonly int $refund,
    ) {}
}

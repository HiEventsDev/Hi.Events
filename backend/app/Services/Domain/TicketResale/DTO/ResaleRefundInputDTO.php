<?php

namespace HiEvents\Services\Domain\TicketResale\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class ResaleRefundInputDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $seller_unit_gross,
        public readonly int $replacement_unit_gross,
        public readonly int $replacement_order_gross,
        public readonly int $replacement_order_costs,
        public readonly int $replacement_paid_ticket_count,
        public readonly int $seller_order_unrefunded_balance,
    ) {}
}

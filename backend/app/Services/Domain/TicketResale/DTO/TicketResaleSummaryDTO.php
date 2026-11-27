<?php

namespace HiEvents\Services\Domain\TicketResale\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class TicketResaleSummaryDTO extends BaseDataObject
{
    public function __construct(
        public readonly ?string $status,
        public readonly ?int $sold_order_id,
        public readonly ?string $sold_order_public_id,
    ) {}
}

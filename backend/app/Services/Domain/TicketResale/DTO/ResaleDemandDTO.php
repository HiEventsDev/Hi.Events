<?php

namespace HiEvents\Services\Domain\TicketResale\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class ResaleDemandDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $product_price_id,
        public readonly int $event_occurrence_id,
        public readonly int $quantity,
    ) {}
}

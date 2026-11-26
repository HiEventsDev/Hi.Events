<?php

namespace HiEvents\Services\Domain\TicketResale\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class TicketResalePriceFactsDTO extends BaseDataObject
{
    public function __construct(
        public readonly bool $is_on_sale,
        public readonly float $seller_unit_price,
        public readonly int $seller_unit_gross,
        public readonly float $current_unit_price,
        public readonly int $current_unit_gross,
    ) {}
}

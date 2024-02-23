<?php

namespace TicketKitten\Service\Common\Ticket\DTO;

use TicketKitten\DataTransferObjects\BaseDTO;

class PriceDTO extends BaseDTO
{
    public function __construct(
        public float $price,
        public ?float $price_before_discount = null,
    )
    {
    }
}

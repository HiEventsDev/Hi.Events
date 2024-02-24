<?php

namespace HiEvents\Http\DataTransferObjects;

use HiEvents\DataTransferObjects\BaseDTO;

class OrderTicketPriceDTO extends BaseDTO
{
    public function __construct(
        public readonly int    $quantity,
        public readonly int    $price_id,
        public readonly ?float $price = null // used for donation tickets
    )
    {
    }
}

<?php

namespace HiEvents\Services\Domain\Ticket\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class PriceDTO extends BaseDTO
{
    public function __construct(
        public float $price,
        public ?float $price_before_discount = null,
    )
    {
    }
}

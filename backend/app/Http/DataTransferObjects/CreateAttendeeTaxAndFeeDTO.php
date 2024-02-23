<?php

namespace TicketKitten\Http\DataTransferObjects;

use TicketKitten\DataTransferObjects\BaseDTO;

class CreateAttendeeTaxAndFeeDTO extends BaseDTO
{
    public function __construct(
        public readonly int   $tax_or_fee_id,
        public readonly float $amount,
    )
    {
    }
}

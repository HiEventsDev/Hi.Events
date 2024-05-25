<?php

namespace HiEvents\Services\Handlers\Attendee\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class CreateAttendeeTaxAndFeeDTO extends BaseDTO
{
    public function __construct(
        public readonly int   $tax_or_fee_id,
        public readonly float $amount,
    )
    {
    }
}

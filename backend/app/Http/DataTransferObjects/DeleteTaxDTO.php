<?php

namespace TicketKitten\Http\DataTransferObjects;

use TicketKitten\DataTransferObjects\BaseDTO;

class DeleteTaxDTO extends BaseDTO
{
    public function __construct(
        public readonly int $taxId,
        public readonly int $accountId,
    )
    {
    }
}

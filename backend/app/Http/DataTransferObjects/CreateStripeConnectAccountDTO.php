<?php

namespace TicketKitten\Http\DataTransferObjects;

use TicketKitten\DataTransferObjects\BaseDTO;

class CreateStripeConnectAccountDTO extends BaseDTO
{
    public function __construct(
        public readonly int $accountId,
    )
    {
    }
}

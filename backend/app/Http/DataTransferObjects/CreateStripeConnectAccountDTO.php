<?php

namespace HiEvents\Http\DataTransferObjects;

use HiEvents\DataTransferObjects\BaseDTO;

class CreateStripeConnectAccountDTO extends BaseDTO
{
    public function __construct(
        public readonly int $accountId,
    )
    {
    }
}

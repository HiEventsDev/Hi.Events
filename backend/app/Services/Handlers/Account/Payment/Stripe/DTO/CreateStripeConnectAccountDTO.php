<?php

namespace HiEvents\Services\Handlers\Account\Payment\Stripe\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class CreateStripeConnectAccountDTO extends BaseDTO
{
    public function __construct(
        public readonly int $accountId,
    )
    {
    }
}

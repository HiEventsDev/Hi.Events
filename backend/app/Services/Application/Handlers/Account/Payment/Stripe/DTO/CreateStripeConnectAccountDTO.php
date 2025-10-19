<?php

namespace HiEvents\Services\Application\Handlers\Account\Payment\Stripe\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class CreateStripeConnectAccountDTO extends BaseDTO
{
    public function __construct(
        public readonly int $accountId,
    )
    {
    }
}

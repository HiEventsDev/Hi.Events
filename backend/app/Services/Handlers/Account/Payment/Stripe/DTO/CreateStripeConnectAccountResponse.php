<?php

namespace HiEvents\Services\Handlers\Account\Payment\Stripe\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\AccountDomainObject;

class CreateStripeConnectAccountResponse extends BaseDTO
{
    public function __construct(
        public string              $stripeAccountId,
        public AccountDomainObject $account,
        public bool                $isConnectSetupComplete,
        public ?string             $connectUrl = null,
    )
    {
    }
}

<?php

namespace TicketKitten\Service\Handler\Account\Payment\Stripe\DataTransferObjects;

use TicketKitten\DataTransferObjects\BaseDTO;
use TicketKitten\DomainObjects\AccountDomainObject;

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

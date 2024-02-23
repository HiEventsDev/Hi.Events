<?php

namespace TicketKitten\Service\Common\Payment\Stripe\DTOs;

use TicketKitten\DataTransferObjects\BaseDTO;
use TicketKitten\DomainObjects\AccountDomainObject;

class CreatePaymentIntentRequestDTO extends BaseDTO
{
    public function __construct(
        public readonly int    $amount,
        public readonly string $currencyCode,
        public AccountDomainObject $account,
    )
    {
    }
}

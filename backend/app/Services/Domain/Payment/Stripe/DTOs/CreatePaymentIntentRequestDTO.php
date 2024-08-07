<?php

namespace HiEvents\Services\Domain\Payment\Stripe\DTOs;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\AccountDomainObject;

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

<?php

namespace HiEvents\Services\Domain\Payment\Stripe\DTOs;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Values\MoneyValue;

class CreatePaymentIntentRequestDTO extends BaseDTO
{
    public function __construct(
        public readonly MoneyValue $amount,
        public readonly string     $currencyCode,
        public AccountDomainObject $account,
        public OrderDomainObject   $order,
    )
    {
    }
}

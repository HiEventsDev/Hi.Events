<?php

namespace HiEvents\Services\Domain\Payment\Stripe\DTOs;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\AccountDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\DomainObjects\OrganizerVatSettingDomainObject;
use HiEvents\Values\MoneyValue;

class CreatePaymentIntentRequestDTO extends BaseDTO
{
    public function __construct(
        public readonly MoneyValue $amount,
        public readonly string $currencyCode,
        public readonly AccountDomainObject $account,
        public readonly OrderDomainObject $order,
        public readonly ?OrganizerConfigurationDomainObject $configuration = null,
        public readonly ?string $stripeAccountId = null,
        public readonly ?OrganizerVatSettingDomainObject $vatSettings = null,
        public readonly ?string $description = null,
    ) {}
}

<?php

namespace HiEvents\Services\Application\Handlers\Account\Subscription\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\SubscriptionBillingInterval;

class CreateSubscriptionCheckoutSessionDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $accountId,
        public readonly SubscriptionBillingInterval $interval,
    ) {}
}

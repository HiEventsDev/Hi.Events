<?php

namespace HiEvents\Services\Domain\Subscription\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\SubscriptionBillingInterval;

class SubscriptionPriceDTO extends BaseDataObject
{
    public function __construct(
        public readonly SubscriptionBillingInterval $interval,
        public readonly string $priceId,
        public readonly int $unitAmount,
        public readonly string $currency,
    ) {}
}

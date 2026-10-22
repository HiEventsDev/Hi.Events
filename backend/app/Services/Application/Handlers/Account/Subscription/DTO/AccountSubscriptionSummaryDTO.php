<?php

namespace HiEvents\Services\Application\Handlers\Account\Subscription\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\Services\Domain\Subscription\DTO\SubscriptionPriceDTO;

class AccountSubscriptionSummaryDTO extends BaseDataObject
{
    public function __construct(
        public readonly bool $available,
        public readonly bool $active,
        public readonly ?string $status = null,
        public readonly ?string $currentPeriodEnd = null,
        public readonly bool $cancelAtPeriodEnd = false,
        /** @var SubscriptionPriceDTO[] */
        public readonly array $prices = [],
    ) {}
}

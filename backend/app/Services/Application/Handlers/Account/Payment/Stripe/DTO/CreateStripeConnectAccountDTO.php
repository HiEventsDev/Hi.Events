<?php

namespace HiEvents\Services\Application\Handlers\Account\Payment\Stripe\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\StripePlatform;

class CreateStripeConnectAccountDTO extends BaseDataObject
{
    public function __construct(
        public readonly int                 $accountId,
        public readonly StripePlatform|null $platform = null,
    )
    {
    }
}

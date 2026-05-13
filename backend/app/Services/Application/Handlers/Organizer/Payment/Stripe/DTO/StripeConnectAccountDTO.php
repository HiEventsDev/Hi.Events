<?php

namespace HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\StripePlatform;

class StripeConnectAccountDTO extends BaseDataObject
{
    public function __construct(
        public readonly ?string         $stripeAccountId = null,
        public readonly ?string         $connectUrl = null,
        public readonly bool            $isSetupComplete = false,
        public readonly ?StripePlatform $platform = null,
        public readonly ?string         $accountType = null,
        public readonly bool            $isPrimary = false,
        public readonly ?string         $country = null,
        public readonly ?string         $businessType = null,
        public readonly bool            $chargesEnabled = false,
        public readonly bool            $payoutsEnabled = false,
        public readonly array           $capabilities = [],
        public readonly array           $requirements = [],
    )
    {
    }
}

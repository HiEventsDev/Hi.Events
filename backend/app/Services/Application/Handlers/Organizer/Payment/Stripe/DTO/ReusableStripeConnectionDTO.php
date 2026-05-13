<?php

namespace HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class ReusableStripeConnectionDTO extends BaseDataObject
{
    public function __construct(
        public readonly int     $organizerId,
        public readonly string  $organizerName,
        public readonly string  $stripeAccountId,
        public readonly ?string $platform = null,
        public readonly ?string $country = null,
        public readonly ?string $businessType = null,
    )
    {
    }
}

<?php

namespace HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\OrganizerDomainObject;

class CreateStripeConnectAccountResponse extends BaseDataObject
{
    public function __construct(
        public string $stripeConnectAccountType,
        public string $stripeAccountId,
        public OrganizerDomainObject $organizer,
        public bool $isConnectSetupComplete,
        public ?string $connectUrl = null,
    ) {}
}

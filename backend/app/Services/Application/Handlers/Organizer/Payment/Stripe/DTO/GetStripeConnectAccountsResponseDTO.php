<?php

namespace HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use Illuminate\Support\Collection;

class GetStripeConnectAccountsResponseDTO extends BaseDataObject
{
    public function __construct(
        public readonly OrganizerDomainObject $organizer,
        public readonly Collection $stripeConnectAccounts,
        public readonly Collection $reusableConnections,
        public readonly ?string $primaryStripeAccountId = null,
        public readonly bool $hasCompletedSetup = false,
    ) {}
}

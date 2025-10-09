<?php

namespace HiEvents\Services\Application\Handlers\Account\Payment\Stripe\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\AccountDomainObject;
use Illuminate\Support\Collection;

class GetStripeConnectAccountsResponseDTO extends BaseDataObject
{
    public function __construct(
        public readonly AccountDomainObject $account,
        public readonly Collection $stripeConnectAccounts,
        public readonly ?string $primaryStripeAccountId = null,
        public readonly bool $hasCompletedSetup = false,
    ) {
    }
}
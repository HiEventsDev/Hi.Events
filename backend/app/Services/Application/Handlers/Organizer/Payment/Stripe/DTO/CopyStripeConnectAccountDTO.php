<?php

namespace HiEvents\Services\Application\Handlers\Organizer\Payment\Stripe\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class CopyStripeConnectAccountDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $targetOrganizerId,
        public readonly int $sourceOrganizerId,
        public readonly int $accountId,
    ) {}
}

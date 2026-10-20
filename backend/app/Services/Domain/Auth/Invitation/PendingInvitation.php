<?php

namespace HiEvents\Services\Domain\Auth\Invitation;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\UserDomainObject;

class PendingInvitation extends BaseDataObject
{
    public function __construct(
        public readonly UserDomainObject $user,
        public readonly int $accountId,
        public readonly bool $requiresSignIn,
        public readonly string $accountName,
    ) {}
}

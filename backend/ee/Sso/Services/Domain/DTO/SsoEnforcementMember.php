<?php

namespace HiEvents\Enterprise\Sso\Services\Domain\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SsoEnforcementMember extends BaseDataObject
{
    public function __construct(
        public readonly int $accountUserId,
        public readonly int $userId,
        public readonly string $email,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly string $role,
        public readonly bool $isAccountOwner,
        public readonly bool $onVerifiedDomain,
        public readonly bool $ssoEnforced,
        public readonly bool $ssoAvailable,
    ) {}
}

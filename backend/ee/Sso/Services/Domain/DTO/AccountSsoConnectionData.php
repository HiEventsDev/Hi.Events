<?php

namespace HiEvents\Enterprise\Sso\Services\Domain\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\AccountSsoConnectionType;
use HiEvents\DomainObjects\Enums\Role;

class AccountSsoConnectionData extends BaseDataObject
{
    public function __construct(
        public readonly AccountSsoConnectionType $type,
        public readonly string $clientId,
        public readonly ?string $clientSecret = null,
        public readonly ?string $issuer = null,
        public readonly ?string $tenantId = null,
        public readonly ?string $hostedDomain = null,
        public readonly int $sessionTtlMinutes = 720,
        public readonly bool $jitProvisioningEnabled = false,
        public readonly Role $jitDefaultRole = Role::ORGANIZER,
    ) {}
}

<?php

namespace HiEvents\Services\Domain\Auth\Sso\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\SsoProviderType;

class SsoProviderConfig extends BaseDataObject
{
    public function __construct(
        public readonly string $key,
        public readonly SsoProviderType $type,
        public readonly string $name,
        public readonly string $issuer,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly ?string $logoUrl,
        public readonly bool $trustEmail,
        public readonly bool $restrictNetwork = false,
        public readonly ?int $connectionId = null,
        public readonly ?int $accountId = null,
        public readonly ?string $tenantId = null,
        public readonly ?string $hostedDomain = null,
    ) {}

    public function isAccountConnection(): bool
    {
        return $this->connectionId !== null;
    }
}

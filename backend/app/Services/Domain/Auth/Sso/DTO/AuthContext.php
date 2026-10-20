<?php

namespace HiEvents\Services\Domain\Auth\Sso\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\AuthMethod;

class AuthContext extends BaseDataObject
{
    public function __construct(
        public readonly AuthMethod $method = AuthMethod::PASSWORD,
        public readonly ?SsoIdentityClaims $pendingIdentity = null,
        public readonly ?string $bindingHash = null,
        public readonly ?int $ssoConnectionId = null,
        public readonly bool $secondFactorVerified = false,
        public readonly ?int $accountId = null,
    ) {}

    public static function password(): self
    {
        return new self;
    }

    public function withSecondFactorVerified(): self
    {
        return new self(
            method: $this->method,
            pendingIdentity: $this->pendingIdentity,
            bindingHash: $this->bindingHash,
            ssoConnectionId: $this->ssoConnectionId,
            secondFactorVerified: true,
            accountId: $this->accountId,
        );
    }
}

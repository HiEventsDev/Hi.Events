<?php

namespace HiEvents\Services\Domain\Auth\Sso\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\SsoIntent;

class SsoStateDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $providerKey,
        public readonly SsoIntent $intent,
        public readonly string $codeVerifier,
        public readonly string $nonce,
        public readonly ?int $userId = null,
        public readonly ?string $tokenId = null,
        public readonly ?string $invitationToken = null,
        public readonly ?string $returnPath = null,
        public readonly ?string $timezone = null,
        public readonly bool $marketingOptIn = false,
    ) {}
}

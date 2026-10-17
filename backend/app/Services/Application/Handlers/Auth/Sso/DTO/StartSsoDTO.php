<?php

namespace HiEvents\Services\Application\Handlers\Auth\Sso\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\SsoIntent;

class StartSsoDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $providerKey,
        public readonly SsoIntent $intent,
        public readonly ?string $returnPath = null,
        public readonly ?string $invitationToken = null,
        public readonly ?string $timezone = null,
        public readonly bool $marketingOptIn = false,
        public readonly ?int $userId = null,
        public readonly ?string $tokenId = null,
        public readonly bool $switchAccount = false,
        public readonly ?string $loginHint = null,
        public readonly bool $silent = false,
    ) {}
}

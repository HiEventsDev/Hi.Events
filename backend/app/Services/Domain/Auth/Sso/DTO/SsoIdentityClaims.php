<?php

namespace HiEvents\Services\Domain\Auth\Sso\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SsoIdentityClaims extends BaseDataObject
{
    public function __construct(
        public readonly string $providerKey,
        public readonly string $issuer,
        public readonly string $subject,
        public readonly ?string $email,
        public readonly bool $emailTrusted,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly ?int $authTime,
        public readonly ?int $connectionId = null,
        public readonly array $groups = [],
    ) {}
}

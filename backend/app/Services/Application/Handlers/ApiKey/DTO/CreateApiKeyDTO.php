<?php

namespace HiEvents\Services\Application\Handlers\ApiKey\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\ApiKeyAccess;

class CreateApiKeyDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $userId,
        public readonly int $accountId,
        public readonly string $name,
        public readonly ApiKeyAccess $access,
        public readonly ?int $expiresInDays,
        public readonly bool $canEdit,
        public readonly ?string $password = null,
        public readonly ?string $reauthProof = null,
        public readonly ?string $tokenId = null,
    ) {}
}

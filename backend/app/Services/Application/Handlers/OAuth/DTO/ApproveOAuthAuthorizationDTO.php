<?php

namespace HiEvents\Services\Application\Handlers\OAuth\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\ApiKeyAccess;

class ApproveOAuthAuthorizationDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $requestId,
        public readonly int $userId,
        public readonly int $accountId,
        public readonly ApiKeyAccess $access,
        public readonly bool $canEdit,
        public readonly ?string $password = null,
        public readonly ?string $reauthProof = null,
        public readonly ?string $tokenId = null,
    ) {}
}

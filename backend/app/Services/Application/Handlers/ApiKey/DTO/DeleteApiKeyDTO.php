<?php

namespace HiEvents\Services\Application\Handlers\ApiKey\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class DeleteApiKeyDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $apiKeyId,
        public readonly int $accountId,
        public readonly int $userId,
        public readonly bool $canManageTeam,
    ) {}
}

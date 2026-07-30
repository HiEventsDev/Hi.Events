<?php

namespace HiEvents\Services\Application\Handlers\Account\DeletionRequest\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\AccountDeletionInitiator;

class RequestAccountDeletionDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $accountId,
        public readonly int $requestedByUserId,
        public readonly AccountDeletionInitiator $initiatedBy,
        public readonly string $confirmation,
        public readonly ?string $reason = null,
    ) {}
}

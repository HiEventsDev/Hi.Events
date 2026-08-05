<?php

namespace HiEvents\Services\Application\Handlers\Account\DeletionRequest\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\AccountDeletionRequestDomainObject;

class GetAccountDeletionStatusDTO extends BaseDataObject
{
    public function __construct(
        public readonly ?AccountDeletionRequestDomainObject $activeRequest,
        public readonly bool $canRequestDeletion,
        public readonly ?string $cannotDeleteReason,
        public readonly string $expectedOutcome,
    ) {}
}

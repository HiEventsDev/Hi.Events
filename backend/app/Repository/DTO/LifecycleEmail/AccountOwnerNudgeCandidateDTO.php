<?php

declare(strict_types=1);

namespace HiEvents\Repository\DTO\LifecycleEmail;

use HiEvents\DataTransferObjects\BaseDataObject;

class AccountOwnerNudgeCandidateDTO extends BaseDataObject
{
    public function __construct(
        public int $accountId,
        public int $userId,
        public string $email,
        public string $firstName,
    ) {}
}

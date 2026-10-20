<?php

declare(strict_types=1);

namespace HiEvents\Repository\DTO\LifecycleEmail;

use HiEvents\DataTransferObjects\BaseDataObject;

class EventNudgeCandidateDTO extends BaseDataObject
{
    public function __construct(
        public int $accountId,
        public int $userId,
        public int $eventId,
        public string $eventTitle,
        public string $email,
        public string $firstName,
    ) {}
}

<?php

declare(strict_types=1);

namespace HiEvents\Repository\DTO\LifecycleEmail;

use HiEvents\DataTransferObjects\BaseDataObject;

class LapsedPayerNudgeCandidateDTO extends BaseDataObject
{
    public function __construct(
        public int $accountId,
        public int $userId,
        public int $eventId,
        public int $organizerId,
        public string $eventTitle,
        public bool $isEventArchived,
        public string $lastSoldDateEndedAt,
        public string $email,
        public string $firstName,
    ) {}
}

<?php

declare(strict_types=1);

namespace HiEvents\Repository\DTO\LifecycleEmail;

use HiEvents\DataTransferObjects\BaseDataObject;

class StripeOnboardingNudgeCandidateDTO extends BaseDataObject
{
    public function __construct(
        public int $accountId,
        public int $userId,
        public int $organizerId,
        public string $organizerName,
        public string $email,
        public string $firstName,
    ) {}
}

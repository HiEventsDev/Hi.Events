<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\LifecycleEmail\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class LifecycleNudgeCandidateDTO extends BaseDataObject
{
    public function __construct(
        public int $accountId,
        public string $email,
        public LifecycleEmailRecipientDTO $recipient,
        public ?int $eventId = null,
    ) {}
}

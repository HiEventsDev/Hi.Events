<?php

namespace HiEvents\Services\Domain\Account\Anonymization;

readonly class AnonymizationContext
{
    public function __construct(
        public int $accountId,
        public array $eventIds,
        public array $orderIds,
        public array $organizerIds,
        public array $soleUserIds,
        public array $sharedUserIds,
        public array $soleUserEmails,
        public array $stripeAccountIds,
        public array $imageFiles,
    ) {}
}

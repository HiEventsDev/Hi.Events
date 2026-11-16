<?php

namespace HiEvents\Services\Domain\OrganizerSubscription\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class SubscriptionConfirmationData extends BaseDataObject
{
    public function __construct(
        public readonly int $id,
        public readonly int $organizerId,
        public readonly string $email,
        public readonly string $locale,
        public readonly string $token,
    ) {}
}

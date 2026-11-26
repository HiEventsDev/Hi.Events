<?php

namespace HiEvents\Services\Domain\Abuse\DTO;

use Carbon\CarbonImmutable;
use HiEvents\DataTransferObjects\BaseDataObject;

class SuspiciousActivityAccountDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $email,
        public readonly CarbonImmutable $createdAt,
        public readonly bool $isVerified,
        public readonly bool $hasStripe,
        public readonly int $liveEvents,
        public readonly bool $isSuspended,
    ) {}
}

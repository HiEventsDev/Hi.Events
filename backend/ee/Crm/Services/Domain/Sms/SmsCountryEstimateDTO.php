<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Sms;

use HiEvents\DataTransferObjects\BaseDataObject;

class SmsCountryEstimateDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $country,
        public readonly int $recipients,
        public readonly int $parts,
        public readonly bool $is_sending_now,
        public readonly string $next_sending_at,
    ) {}
}

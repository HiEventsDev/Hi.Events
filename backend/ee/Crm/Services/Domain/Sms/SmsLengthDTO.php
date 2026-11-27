<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Sms;

use HiEvents\DataTransferObjects\BaseDataObject;

class SmsLengthDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $characters,
        public readonly int $parts,
        public readonly bool $is_unicode,
    ) {}
}

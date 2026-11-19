<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Sms;

use HiEvents\DataTransferObjects\BaseDataObject;

class SmsSenderDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $sid,
        public readonly string $name,
    ) {}
}

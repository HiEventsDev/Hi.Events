<?php

declare(strict_types=1);

namespace HiEvents\Repository\DTO\LifecycleEmail;

use HiEvents\DataTransferObjects\BaseDataObject;

class LifecycleNudgeSendCountsDTO extends BaseDataObject
{
    public function __construct(
        public int $total,
        public int $lastThirtyDays,
    ) {}
}

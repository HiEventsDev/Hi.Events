<?php

declare(strict_types=1);

namespace HiEvents\Repository\DTO\EmailDelivery;

use HiEvents\DataTransferObjects\BaseDataObject;

class EmailDeliveryCategoryStatsDTO extends BaseDataObject
{
    public function __construct(
        public ?string $category,
        public int $sent,
        public int $delivered,
        public int $permanentBounces,
        public int $transientBounces,
        public int $complaints,
        public int $opened,
        public int $clicked,
    ) {}
}

<?php

declare(strict_types=1);

namespace HiEvents\Repository\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class BadgeStatsDTO extends BaseDataObject
{
    public function __construct(
        public int $total,
        public int $printed,
        public int $not_printed,
        public int $needs_reprint,
    ) {}
}

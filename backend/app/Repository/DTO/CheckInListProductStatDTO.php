<?php

namespace HiEvents\Repository\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class CheckInListProductStatDTO extends BaseDTO
{
    public function __construct(
        public int $productId,
        public string $productTitle,
        public int $totalAttendees,
        public int $checkedInAttendees,
    ) {}
}

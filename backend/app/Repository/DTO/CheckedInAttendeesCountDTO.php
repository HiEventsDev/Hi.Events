<?php

namespace HiEvents\Repository\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class CheckedInAttendeesCountDTO extends BaseDTO
{
    public function __construct(
        public int $checkInListId,
        public int $checkedInCount,
        public int $totalAttendeesCount,
    )
    {
    }
}

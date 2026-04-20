<?php

namespace HiEvents\Repository\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class CheckInListRecentCheckInDTO extends BaseDTO
{
    public function __construct(
        public string  $attendeePublicId,
        public string  $firstName,
        public string  $lastName,
        public ?string $productTitle,
        public string  $checkedInAt,
    )
    {
    }
}

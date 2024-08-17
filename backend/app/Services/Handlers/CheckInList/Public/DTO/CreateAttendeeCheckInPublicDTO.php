<?php

namespace HiEvents\Services\Handlers\CheckInList\Public\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class CreateAttendeeCheckInPublicDTO extends BaseDTO
{
    public function __construct(
        public string $checkInListUuid,
        public string $checkInUserIpAddress,
        public array $attendeePublicIds,
    )
    {
    }
}

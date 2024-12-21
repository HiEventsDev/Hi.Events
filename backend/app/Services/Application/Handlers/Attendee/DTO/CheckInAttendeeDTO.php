<?php

namespace HiEvents\Services\Application\Handlers\Attendee\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class CheckInAttendeeDTO extends BaseDTO
{
    public function __construct(
        public string $attendee_public_id,
        public int    $event_id,
        public string $action,
        public int    $checked_in_by_user_id,
    )
    {
    }
}

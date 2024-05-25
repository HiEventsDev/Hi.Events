<?php

namespace HiEvents\Services\Handlers\Attendee\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class PartialEditAttendeeDTO extends BaseDTO
{
    public function __construct(
        public int $attendee_id,
        public int $event_id,

        public ?string $first_name,
        public ?string $last_name,
        public ?string $email,
        public ?string $status,
    )
    {
    }
}

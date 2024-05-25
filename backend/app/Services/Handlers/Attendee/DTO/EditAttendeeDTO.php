<?php

namespace HiEvents\Services\Handlers\Attendee\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class EditAttendeeDTO extends BaseDTO
{
    public function __construct(
        public string $first_name,
        public string $last_name,
        public string $email,
        public int    $ticket_id,
        public int    $ticket_price_id,
        public int    $event_id,
        public int    $attendee_id,
    )
    {
    }
}

<?php

namespace HiEvents\Services\Application\Handlers\Attendee\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class ResendAttendeeTicketDTO extends BaseDTO
{
    public function __construct(
        public int $attendeeId,
        public int $eventId,
    )
    {
    }
}

<?php

namespace TicketKitten\Service\Common\Event\DTO;

use TicketKitten\DataTransferObjects\BaseDTO;

class EventCheckInStatsResponseDTO extends BaseDTO
{
    public function __construct(
        public readonly int $total_checked_in_attendees,
        public readonly int $total_attendees,
    )
    {
    }
}

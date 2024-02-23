<?php

namespace TicketKitten\Http\DataTransferObjects;

use TicketKitten\DataTransferObjects\BaseDTO;

class UpdateEventStatusDTO extends BaseDTO
{
    public function __construct(
        public string $status,
        public int $eventId,
        public int $accountId,
    )
    {
    }
}

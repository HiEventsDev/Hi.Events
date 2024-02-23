<?php

namespace TicketKitten\Http\DataTransferObjects;

use TicketKitten\DataTransferObjects\BaseDTO;

class CancelOrderDTO extends BaseDTO
{
    public function __construct(
        public int $eventId,
        public int $orderId
    )
    {
    }
}

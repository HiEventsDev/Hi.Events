<?php

namespace TicketKitten\Http\DataTransferObjects;

use Illuminate\Support\Collection;
use TicketKitten\DataTransferObjects\Attributes\CollectionOf;
use TicketKitten\DataTransferObjects\BaseDTO;

class CompleteOrderDTO extends BaseDTO
{
    /**
     * @param CompleteOrderOrderDTO $order
     * @param Collection<CompleteOrderAttendeeDTO> $attendees
     */
    public function __construct(
        public CompleteOrderOrderDTO $order,
        #[CollectionOf(CompleteOrderAttendeeDTO::class)]
        public Collection            $attendees
    )
    {
    }
}

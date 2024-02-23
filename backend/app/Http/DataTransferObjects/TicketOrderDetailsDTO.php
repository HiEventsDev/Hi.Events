<?php

namespace TicketKitten\Http\DataTransferObjects;

use Illuminate\Support\Collection;
use TicketKitten\DataTransferObjects\Attributes\CollectionOf;
use TicketKitten\DataTransferObjects\BaseDTO;

class TicketOrderDetailsDTO extends BaseDTO
{
    public function __construct(
        public readonly int $ticket_id,
        #[CollectionOf(OrderTicketPriceDTO::class)]
        public Collection   $quantities,
    )
    {
    }
}

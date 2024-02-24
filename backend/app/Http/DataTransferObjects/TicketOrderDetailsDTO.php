<?php

namespace HiEvents\Http\DataTransferObjects;

use Illuminate\Support\Collection;
use HiEvents\DataTransferObjects\Attributes\CollectionOf;
use HiEvents\DataTransferObjects\BaseDTO;

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

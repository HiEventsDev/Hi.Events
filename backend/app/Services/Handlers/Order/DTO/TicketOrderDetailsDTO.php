<?php

namespace HiEvents\Services\Handlers\Order\DTO;

use HiEvents\DataTransferObjects\Attributes\CollectionOf;
use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\Services\Domain\Ticket\DTO\OrderTicketPriceDTO;
use Illuminate\Support\Collection;

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

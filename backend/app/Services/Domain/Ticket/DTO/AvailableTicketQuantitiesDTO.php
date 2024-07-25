<?php

namespace HiEvents\Services\Domain\Ticket\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class AvailableTicketQuantitiesDTO extends BaseDTO
{
    public function __construct(
        public int     $ticket_id,
        public int     $price_id,
        public string  $ticket_title,
        public ?string $price_label,
        public int     $quantity_available,
    )
    {
    }
}

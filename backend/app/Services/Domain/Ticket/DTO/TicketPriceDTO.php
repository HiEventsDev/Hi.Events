<?php

namespace HiEvents\Services\Domain\Ticket\DTO;

use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\DomainObjects\Status\TicketStatus;

class TicketPriceDTO extends BaseDTO
{
    public function __construct(
        public readonly float        $price,
        public readonly ?string      $label = null,
        public readonly ?string      $sale_start_date = null,
        public readonly ?string      $sale_end_date = null,
        public readonly ?int         $initial_quantity_available = null,
        public readonly ?bool        $is_hidden = false,
        public readonly ?int         $id = null,
        public readonly TicketStatus $status = TicketStatus::ACTIVE,
    )
    {
    }
}

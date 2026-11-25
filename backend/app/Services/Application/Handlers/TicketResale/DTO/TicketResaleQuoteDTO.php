<?php

namespace HiEvents\Services\Application\Handlers\TicketResale\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;
use HiEvents\DomainObjects\Enums\TicketResaleBlock;
use HiEvents\Services\Domain\TicketResale\DTO\TicketResaleEstimateDTO;

class TicketResaleQuoteDTO extends BaseDataObject
{
    public function __construct(
        public readonly ?TicketResaleBlock $block,
        public readonly TicketResaleEstimateDTO $estimate,
        public readonly string $cutoff,
    ) {}
}

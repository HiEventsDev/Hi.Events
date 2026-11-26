<?php

namespace HiEvents\Services\Application\Handlers\TicketResale\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class TicketResaleListingActionDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $event_id,
        public readonly int $listing_id,
    ) {}
}

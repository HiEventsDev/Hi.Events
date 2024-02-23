<?php

namespace TicketKitten\Http\DataTransferObjects;

use TicketKitten\DataTransferObjects\BaseDTO;

class PartialUpdateEventSettingsDTO extends BaseDTO
{
    public function __construct(
        public readonly int   $account_id,
        public readonly int   $event_id,
        public readonly array $settings,
    )
    {
    }
}

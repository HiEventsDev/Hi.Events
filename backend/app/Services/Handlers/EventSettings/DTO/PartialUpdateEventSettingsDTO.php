<?php

namespace HiEvents\Services\Handlers\EventSettings\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

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

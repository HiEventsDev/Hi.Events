<?php

namespace HiEvents\Services\Handlers\Event\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class EventStatsRequestDTO extends BaseDTO
{
    public function __construct(
        public int    $event_id,
        public string $start_date,
        public string $end_date,
    )
    {
    }
}

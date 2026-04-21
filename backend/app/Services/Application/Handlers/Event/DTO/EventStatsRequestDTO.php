<?php

namespace HiEvents\Services\Application\Handlers\Event\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class EventStatsRequestDTO extends BaseDTO
{
    public function __construct(
        public int     $event_id,
        public ?string $start_date = null,
        public ?string $end_date = null,
        public string  $date_range_preset = 'month',
    )
    {
    }
}

<?php

namespace HiEvents\Services\Handlers\Event\DTO;

use HiEvents\DataTransferObjects\Attributes\CollectionOf;
use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\Services\Domain\Event\DTO\EventCheckInStatsResponseDTO;
use HiEvents\Services\Domain\Event\DTO\EventDailyStatsResponseDTO;
use Illuminate\Support\Collection;

class EventStatsResponseDTO extends BaseDTO
{
    public function __construct(
        #[CollectionOf(EventDailyStatsResponseDTO::class)]
        public readonly Collection          $daily_stats,
        public readonly string              $start_date,
        public readonly string              $end_date,

        public EventCheckInStatsResponseDTO $check_in_stats,

        public int                          $total_tickets_sold,
        public int                          $total_orders,
        public float                        $total_gross_sales,
        public float                        $total_fees,
        public float                        $total_tax,
        public float                        $total_views,
    )
    {
    }
}

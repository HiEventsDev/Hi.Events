<?php

namespace HiEvents\Http\DataTransferObjects;

use Illuminate\Support\Collection;
use HiEvents\DataTransferObjects\Attributes\CollectionOf;
use HiEvents\DataTransferObjects\BaseDTO;
use HiEvents\Service\Common\Event\DTO\EventCheckInStatsResponseDTO;

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
    )
    {
    }
}

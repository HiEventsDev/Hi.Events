<?php

namespace HiEvents\Services\Domain\Event\DTO;

readonly class EventDailyStatsResponseDTO
{
    public function __construct(
        public string $date,
        public float  $total_fees,
        public float  $total_tax,
        public float  $total_sales_gross,
        public int    $tickets_sold,
        public int    $orders_created,
    )
    {
    }
}

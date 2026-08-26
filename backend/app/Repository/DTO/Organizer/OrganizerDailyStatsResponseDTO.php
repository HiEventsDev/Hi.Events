<?php

namespace HiEvents\Repository\DTO\Organizer;

readonly class OrganizerDailyStatsResponseDTO
{
    public function __construct(
        public string $date,
        public int $attendees_registered,
        public int $products_sold,
        public float $total_sales_gross,
        public int $orders_created,
        public float $total_refunded,
    ) {}
}

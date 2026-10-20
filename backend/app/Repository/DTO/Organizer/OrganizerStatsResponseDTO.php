<?php

namespace HiEvents\Repository\DTO\Organizer;

use Illuminate\Support\Collection;

class OrganizerStatsResponseDTO
{
    public function __construct(
        public int $total_products_sold,
        public int $total_attendees_registered,

        public int $total_orders,
        public float $total_gross_sales,
        public float $total_fees,
        public float $total_tax,
        public float $total_views,
        public float $total_refunded,

        public string $currency_code,

        public Collection $daily_stats,
        public string $start_date,
        public string $end_date,

        public array $all_organizers_currencies = [],
    ) {}
}

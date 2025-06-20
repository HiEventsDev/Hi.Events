<?php

namespace HiEvents\Repository\DTO\Organizer;

class OrganizerStatsResponseDTO
{
    public function __construct(
        public int   $total_products_sold,
        public int   $total_attendees_registered,

        public int   $total_orders,
        public float $total_gross_sales,
        public float $total_fees,
        public float $total_tax,
        public float $total_views,
        public float $total_refunded,

        public string $currency_code,

        public array $all_organizers_currencies = [],
    )
    {
    }
}

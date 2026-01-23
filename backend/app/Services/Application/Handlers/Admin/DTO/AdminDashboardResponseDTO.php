<?php

namespace HiEvents\Services\Application\Handlers\Admin\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class AdminDashboardResponseDTO extends BaseDataObject
{
    public function __construct(
        public readonly array $popular_events,
        public readonly array $most_viewed_events,
        public readonly array $top_organizers,
        public readonly array $recent_accounts,
        public readonly float $recent_revenue,
        public readonly int $recent_orders_count,
        public readonly float $recent_orders_total,
        public readonly int $recent_signups_count,
    ) {
    }
}

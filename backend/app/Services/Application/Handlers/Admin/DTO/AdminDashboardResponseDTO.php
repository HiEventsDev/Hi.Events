<?php

namespace HiEvents\Services\Application\Handlers\Admin\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class AdminDashboardResponseDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $period,
        public readonly array $attention,
        public readonly array $commission_by_currency,
        public readonly string $views_since,
        public readonly array $sales_by_currency,
        public readonly int $orders_count,
        public readonly int $tickets_sold,
        public readonly int $signups_count,
        public readonly int $events_created_count,
        public readonly array $top_selling_events,
        public readonly array $top_accounts_by_commission,
        public readonly array $most_viewed_events,
        public readonly array $upcoming_events,
        public readonly array $recent_accounts,
    ) {}
}

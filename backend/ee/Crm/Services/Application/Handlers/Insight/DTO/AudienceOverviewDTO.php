<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Insight\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class AudienceOverviewDTO extends BaseDataObject
{
    /**
     * @param  array<int, array{month: string, new_buyers: int, returning_buyers: int}>  $monthly_buyers
     * @param  array<int, array{id: int, title: string}>  $live_events_not_collecting_opt_in
     * @param  array<int, array{currency: string, total_net: float, top_decile_net: float, top_decile_share: float}>|null  $revenue_concentration
     */
    public function __construct(
        public readonly int $contacts_count,
        public readonly int $mailable_count,
        public readonly int $buyers_count,
        public readonly int $repeat_buyers_count,
        public readonly float $repeat_rate,
        public readonly int $new_contacts_count,
        public readonly int $orders_count,
        public readonly int $tickets_count,
        public readonly float $average_events_per_buyer,
        public readonly bool $marketing_opt_in_enabled,
        public readonly int $live_events_count,
        public readonly int $live_events_collecting_opt_in,
        public readonly array $live_events_not_collecting_opt_in,
        public readonly array $monthly_buyers,
        public readonly ?array $revenue_concentration,
    ) {}
}

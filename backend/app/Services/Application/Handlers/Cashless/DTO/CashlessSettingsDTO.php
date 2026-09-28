<?php

namespace HiEvents\Services\Application\Handlers\Cashless\DTO;

use HiEvents\DataTransferObjects\BaseDataObject;

class CashlessSettingsDTO extends BaseDataObject
{
    public function __construct(
        public int $event_id,
        public bool $cashless_enabled,
        public ?int $cashless_topup_product_id,
        public float $cashless_min_topup_amount,
        public bool $cashless_allow_remaining_balance_refund,
        public ?string $cashless_refund_deadline_at,
        public bool $cashless_online_topup_enabled,
        public ?string $cashless_closed_at,
        /** @var array<int> */
        public array $cashless_topup_tax_and_fee_ids,
    ) {}
}

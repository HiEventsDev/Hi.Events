<?php

declare(strict_types=1);

namespace HiEvents\Resources\Cashless;

use HiEvents\Resources\BaseResource;
use HiEvents\Services\Application\Handlers\Cashless\DTO\CashlessSettingsDTO;
use Illuminate\Http\Request;

/**
 * @mixin CashlessSettingsDTO
 */
class CashlessSettingsResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'event_id' => $this->event_id,
            'cashless_enabled' => $this->cashless_enabled,
            'cashless_topup_product_id' => $this->cashless_topup_product_id,
            'cashless_min_topup_amount' => $this->cashless_min_topup_amount,
            'cashless_allow_remaining_balance_refund' => $this->cashless_allow_remaining_balance_refund,
            'cashless_refund_deadline_at' => $this->cashless_refund_deadline_at,
            'cashless_online_topup_enabled' => $this->cashless_online_topup_enabled,
            'cashless_closed_at' => $this->cashless_closed_at,
            'cashless_topup_tax_and_fee_ids' => $this->cashless_topup_tax_and_fee_ids,
        ];
    }
}

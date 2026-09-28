<?php

declare(strict_types=1);

namespace HiEvents\Http\Request\Cashless;

use HiEvents\Http\Request\BaseRequest;

class UpdateCashlessSettingsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'cashless_enabled' => ['required', 'boolean'],
            'cashless_min_topup_amount' => ['required', 'numeric', 'min:0.01', 'max:10000'],
            'cashless_allow_remaining_balance_refund' => ['required', 'boolean'],
            'cashless_refund_deadline_at' => ['nullable', 'date'],
            'cashless_online_topup_enabled' => ['required', 'boolean'],
            'cashless_topup_tax_and_fee_ids' => ['present', 'array'],
            'cashless_topup_tax_and_fee_ids.*' => ['required', 'integer'],
        ];
    }
}

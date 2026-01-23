<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderPaymentPlatformFee extends BaseModel
{
    use SoftDeletes;

    protected function getCastMap(): array
    {
        return [
            'fee_rollup' => 'array',
            'payment_platform_fee_amount' => 'float',
            'application_fee_gross_amount' => 'float',
            'application_fee_net_amount' => 'float',
            'application_fee_vat_amount' => 'float',
            'application_fee_vat_rate' => 'float',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

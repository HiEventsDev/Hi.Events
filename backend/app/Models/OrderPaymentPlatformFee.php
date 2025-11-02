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
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

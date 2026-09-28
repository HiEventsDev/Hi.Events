<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashlessTopup extends BaseModel
{
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CashlessWallet::class, 'cashless_wallet_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function getCastMap(): array
    {
        return [
            'amount' => 'float',
        ];
    }
}

<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashlessTransaction extends BaseModel
{
    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CashlessWallet::class, 'cashless_wallet_id');
    }

    public function sales_point(): BelongsTo
    {
        return $this->belongsTo(CashlessSalesPoint::class, 'cashless_sales_point_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CashlessTransactionItem::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function getCastMap(): array
    {
        return [
            'amount' => 'float',
            'balance_after' => 'float',
        ];
    }
}

<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashlessTransactionItem extends BaseModel
{
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(CashlessTransaction::class, 'cashless_transaction_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function getCastMap(): array
    {
        return [
            'unit_price' => 'float',
            'total' => 'float',
        ];
    }
}

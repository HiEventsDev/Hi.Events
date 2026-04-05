<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GiftCard extends BaseModel
{
    use SoftDeletes;

    protected function getCastMap(): array
    {
        return [
            'original_amount' => 'float',
            'balance' => 'float',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(GiftCardUsage::class);
    }
}

<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashlessWallet extends BaseModel
{
    use SoftDeletes;

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashlessTransaction::class);
    }

    protected function getCastMap(): array
    {
        return [
            'balance' => 'float',
            'total_topped_up' => 'float',
            'total_spent' => 'float',
            'total_refunded' => 'float',
        ];
    }
}

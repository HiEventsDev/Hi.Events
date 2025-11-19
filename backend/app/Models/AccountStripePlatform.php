<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property array|null $stripe_account_details
 * @property Carbon|null $stripe_setup_completed_at
 */
class AccountStripePlatform extends BaseModel
{
    use SoftDeletes;

    protected function getCastMap(): array
    {
        return [
            'stripe_account_details' => 'array',
            'stripe_setup_completed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}

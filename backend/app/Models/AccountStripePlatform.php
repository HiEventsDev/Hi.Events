<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountStripePlatform extends BaseModel
{
    use SoftDeletes;

    protected $casts = [
        'stripe_account_details' => 'array',
        'stripe_setup_completed_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
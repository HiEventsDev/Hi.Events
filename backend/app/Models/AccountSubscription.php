<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountSubscription extends BaseModel
{
    protected function getFillableFields(): array
    {
        return [
            'account_id',
            'stripe_customer_id',
            'stripe_subscription_id',
            'status',
            'current_period_end',
            'cancel_at_period_end',
        ];
    }

    protected function getCastMap(): array
    {
        return [
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}

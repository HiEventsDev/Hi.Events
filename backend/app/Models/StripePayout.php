<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class StripePayout extends BaseModel
{
    protected function getTimestampsEnabled(): bool
    {
        return false;
    }

    protected function getCastMap(): array
    {
        return [
            'fee_breakdown' => 'array',
            'metadata' => 'array',
        ];
    }

    protected function getFillableFields(): array
    {
        return [
            'payout_id',
            'stripe_platform',
            'amount_minor',
            'currency',
            'payout_date',
            'payout_status',
            'payout_stripe_fee_minor',
            'payout_net_amount_minor',
            'payout_exchange_rate',
            'balance_transaction_id',
            'total_application_fee_vat_minor',
            'total_application_fee_net_minor',
            'fee_breakdown',
            'metadata',
            'reconciled',
        ];
    }

    public function stripePayments(): HasMany
    {
        return $this->hasMany(StripePayment::class, 'payout_id', 'payout_id');
    }
}

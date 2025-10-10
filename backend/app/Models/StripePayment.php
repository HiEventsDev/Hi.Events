<?php

namespace HiEvents\Models;

use HiEvents\DomainObjects\Generated\StripePaymentDomainObjectAbstract;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StripePayment extends BaseModel
{
    use SoftDeletes;

    protected function getTimestampsEnabled(): bool
    {
        return false;
    }

    protected function getCastMap(): array
    {
        return [
            'last_error' => 'array',
        ];
    }

    protected function getFillableFields(): array
    {
        return [
            StripePaymentDomainObjectAbstract::ORDER_ID,
            StripePaymentDomainObjectAbstract::CHARGE_ID,
            StripePaymentDomainObjectAbstract::PAYMENT_INTENT_ID,
            StripePaymentDomainObjectAbstract::PAYMENT_METHOD_ID,
            StripePaymentDomainObjectAbstract::CONNECTED_ACCOUNT_ID,
            StripePaymentDomainObjectAbstract::STRIPE_PLATFORM,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends BaseModel
{
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function getTimestampsEnabled(): bool
    {
        return false;
    }

    protected function getCastMap(): array
    {
        return [
            'total_before_additions' => 'float',
            'price' => 'float',
            'price_before_discount' => 'float',
            'total_before_discount' => 'float',
            'total_tax' => 'float',
            'total_service_fee' => 'float',
            'total_gross' => 'float',
            'taxes_and_fees_rollup' => 'array',
        ];
    }

    protected function getFillableFields(): array
    {
        return [];
    }

    public function ticket_price(): HasOne
    {
        return $this->hasOne(TicketPrice::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}

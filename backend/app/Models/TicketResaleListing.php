<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketResaleListing extends BaseModel
{
    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function sold_order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'sold_order_id');
    }

    protected function getCastMap(): array
    {
        return [
            'min_unit_price' => 'float',
            'estimated_refund' => 'float',
            'refund_amount' => 'float',
            'withdrawal_requested_at' => 'datetime',
        ];
    }
}

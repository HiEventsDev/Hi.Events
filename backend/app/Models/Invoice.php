<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends BaseModel
{
    protected function getCastMap(): array
    {
        return [
            'taxes_and_fees' => 'array',
            'items' => 'array',
            'total_amount' => 'float',
            'due_date' => 'datetime',
        ];
    }

    protected function getFillableFields(): array
    {
        return [];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderApplicationFee extends BaseModel
{
    protected function getCastMap(): array
    {
        return [
            'metadata' => 'array',
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
}

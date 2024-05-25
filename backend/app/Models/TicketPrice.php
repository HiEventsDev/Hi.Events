<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketPrice extends BaseModel
{
    protected function getCastMap(): array
    {
        return [
            'price' => 'float',
        ];
    }

    protected function getFillableFields(): array
    {
        return [];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}

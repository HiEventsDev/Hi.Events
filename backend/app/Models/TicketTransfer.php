<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketTransfer extends BaseModel
{
    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    protected function getCastMap(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }
}

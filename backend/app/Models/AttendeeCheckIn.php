<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendeeCheckIn extends BaseModel
{
    protected function getCastMap(): array
    {
        return [];
    }

    protected function getFillableFields(): array
    {
        return [];
    }

    public function products(): BelongsTo
    {
        return $this->belongsTo(
            related: Product::class,
        );
    }

    public function checkInList(): BelongsTo
    {
        return $this->belongsTo(CheckInList::class);
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }
}

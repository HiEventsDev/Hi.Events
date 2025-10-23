<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendeeCheckIn extends BaseModel
{
    use SoftDeletes;

    public function products(): BelongsTo
    {
        return $this->belongsTo(
            related: Product::class,
        );
    }

    public function check_in_list(): BelongsTo
    {
        return $this->belongsTo(CheckInList::class);
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }
}

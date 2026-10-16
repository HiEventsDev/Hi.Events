<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSpace extends BaseModel
{
    protected $table = 'event_spaces';

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function event_location(): BelongsTo
    {
        return $this->belongsTo(EventLocation::class, 'event_location_id');
    }
}

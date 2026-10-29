<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventScheduleItemPerformer extends BaseModel
{
    protected $table = 'event_schedule_item_performers';

    public function schedule_item(): BelongsTo
    {
        return $this->belongsTo(EventScheduleItem::class, 'event_schedule_item_id');
    }
}

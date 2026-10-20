<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventScheduleItem extends BaseModel
{
    protected $table = 'event_schedule_items';

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function performers(): HasMany
    {
        return $this->hasMany(EventScheduleItemPerformer::class)->orderBy('sort_order');
    }
}

<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class EventOccurrence extends BaseModel
{
    use SoftDeletes;

    protected $table = 'event_occurrences';

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function order_items(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'event_occurrence_id');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class, 'event_occurrence_id');
    }

    public function check_in_lists(): HasMany
    {
        return $this->hasMany(CheckInList::class, 'event_occurrence_id');
    }

    public function price_overrides(): HasMany
    {
        return $this->hasMany(ProductPriceOccurrenceOverride::class, 'event_occurrence_id');
    }

    public function event_occurrence_statistics(): HasOne
    {
        return $this->hasOne(EventOccurrenceStatistic::class, 'event_occurrence_id');
    }

    public function product_occurrence_visibility(): HasMany
    {
        return $this->hasMany(ProductOccurrenceVisibility::class, 'event_occurrence_id');
    }

    public function event_occurrence_daily_statistics(): HasMany
    {
        return $this->hasMany(EventOccurrenceDailyStatistic::class, 'event_occurrence_id');
    }

    protected function getCastMap(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'is_overridden' => 'boolean',
        ];
    }
}

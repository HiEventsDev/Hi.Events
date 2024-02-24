<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use HiEvents\DomainObjects\Generated\EventDomainObjectAbstract;
use HiEvents\Models\Traits\HasImages;

class Event extends BaseModel
{
    use HasImages;

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class)->orderBy('order');
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function event_settings(): HasOne
    {
        return $this->hasOne(EventSetting::class);
    }

    public static function boot()
    {
        parent::boot();

        static::creating(
            static function (Event $event) {
                $event->user_id = auth()->user()->id;
            }
        );
    }

    protected function getCastMap(): array
    {
        return [
            EventDomainObjectAbstract::START_DATE => 'datetime',
            EventDomainObjectAbstract::END_DATE => 'datetime',
            EventDomainObjectAbstract::ATTRIBUTES => 'array',
            EventDomainObjectAbstract::LOCATION_DETAILS => 'array',
        ];
    }

    protected function getFillableFields(): array
    {
        return [];
    }
}

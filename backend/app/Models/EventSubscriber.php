<?php

namespace HiEvents\Models;

class EventSubscriber extends BaseModel
{
    protected $table = 'event_subscribers';

    protected $fillable = [
        'organizer_id',
        'event_id',
        'email',
        'first_name',
        'last_name',
        'token',
        'source',
        'is_confirmed',
        'confirmed_at',
        'unsubscribed_at',
    ];

    protected $casts = [
        'is_confirmed' => 'boolean',
        'confirmed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];

    public function organizer()
    {
        return $this->belongsTo(Organizer::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }
}

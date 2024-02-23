<?php

namespace TicketKitten\Models\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use TicketKitten\Models\Image;

trait HasImages
{
    public function images(): MorphMany
    {
        return $this->morphMany(related: Image::class, name: 'entity');
    }
}

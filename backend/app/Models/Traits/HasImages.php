<?php

namespace HiEvents\Models\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use HiEvents\Models\Image;

trait HasImages
{
    public function images(): MorphMany
    {
        return $this->morphMany(related: Image::class, name: 'entity');
    }
}

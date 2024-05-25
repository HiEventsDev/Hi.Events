<?php

namespace HiEvents\Models;

use HiEvents\Models\Traits\HasImages;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organizer extends BaseModel
{
    use HasImages;

    protected function getCastMap(): array
    {
        return [];
    }

    protected function getFillableFields(): array
    {
        return [];
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}

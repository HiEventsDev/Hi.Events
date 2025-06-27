<?php

namespace HiEvents\Models;

use HiEvents\Models\Traits\HasImages;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organizer extends BaseModel
{
    use SoftDeletes;
    use HasImages;

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function organizer_settings(): HasOne
    {
        return $this->hasOne(OrganizerSetting::class);
    }
}

<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrganizerConfiguration extends BaseModel
{
    use SoftDeletes;

    protected function getCastMap(): array
    {
        return [
            'application_fees' => 'array',
        ];
    }

    public function organizers(): HasMany
    {
        return $this->hasMany(Organizer::class);
    }
}

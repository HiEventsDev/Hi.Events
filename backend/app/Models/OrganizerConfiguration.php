<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrganizerConfiguration extends BaseModel
{
    use SoftDeletes;

    protected function getCastMap(): array
    {
        return [
            'application_fees' => 'array',
            'features' => 'array',
        ];
    }

    public function organizers(): HasMany
    {
        return $this->hasMany(Organizer::class);
    }

    public function upgrades_to(): BelongsTo
    {
        return $this->belongsTo(OrganizerConfiguration::class, 'upgrades_to_id');
    }

    public function downgrade_options(): HasMany
    {
        return $this->hasMany(OrganizerConfiguration::class, 'upgrades_to_id');
    }
}

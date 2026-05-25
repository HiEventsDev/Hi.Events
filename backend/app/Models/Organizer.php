<?php

namespace HiEvents\Models;

use HiEvents\Models\Traits\HasImages;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
    }

    public function organizer_stripe_platforms(): HasMany
    {
        return $this->hasMany(OrganizerStripePlatform::class);
    }

    public function organizer_vat_setting(): HasOne
    {
        return $this->hasOne(OrganizerVatSetting::class);
    }

    public function organizer_configuration(): BelongsTo
    {
        return $this->belongsTo(OrganizerConfiguration::class, 'organizer_configuration_id');
    }

    public function location_record(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}

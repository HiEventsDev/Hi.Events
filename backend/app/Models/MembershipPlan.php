<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MembershipPlan extends BaseModel
{
    use SoftDeletes;

    protected function getCastMap(): array
    {
        return [
            'price' => 'float',
            'benefits' => 'array',
            'max_events' => 'integer',
            'discount_percentage' => 'integer',
            'includes_priority_booking' => 'boolean',
        ];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }
}

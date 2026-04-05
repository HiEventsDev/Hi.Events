<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Membership extends BaseModel
{
    use SoftDeletes;

    protected function getCastMap(): array
    {
        return [
            'auto_renew' => 'boolean',
            'events_used' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function eventAccess(): HasMany
    {
        return $this->hasMany(MembershipEventAccess::class);
    }
}

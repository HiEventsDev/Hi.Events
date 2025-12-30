<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountMessagingTier extends BaseModel
{
    use SoftDeletes;

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    protected function getFillableFields(): array
    {
        return [
            'name',
            'max_broadcasts_per_24h',
            'max_recipients_per_broadcast',
            'links_allowed',
        ];
    }

    protected function getCastMap(): array
    {
        return [
            'links_allowed' => 'boolean',
        ];
    }
}

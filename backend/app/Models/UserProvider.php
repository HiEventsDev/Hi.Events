<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProvider extends BaseModel
{
    protected function getFillableFields(): array
    {
        return [
            'user_id',
            'provider',
            'provider_id',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

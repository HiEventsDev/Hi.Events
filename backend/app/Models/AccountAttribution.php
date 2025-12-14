<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountAttribution extends BaseModel
{
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    protected function getCastMap(): array
    {
        return [
            'utm_raw' => 'array',
        ];
    }
}

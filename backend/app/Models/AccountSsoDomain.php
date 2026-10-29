<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountSsoDomain extends BaseModel
{
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}

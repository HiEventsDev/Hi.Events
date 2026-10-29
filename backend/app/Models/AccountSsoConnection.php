<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountSsoConnection extends BaseModel
{
    protected function getCastMap(): array
    {
        return [
            'client_secret' => 'encrypted',
            'enforce_sso' => 'boolean',
            'jit_provisioning_enabled' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}

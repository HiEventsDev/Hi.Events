<?php

declare(strict_types=1);

namespace HiEvents\Models;

class UserTrustedDevice extends BaseModel
{
    protected function getFillableFields(): array
    {
        return [
            'user_id',
            'token_hash',
            'user_agent',
            'ip_address',
            'last_used_at',
            'expires_at',
        ];
    }

    protected function getCastMap(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}

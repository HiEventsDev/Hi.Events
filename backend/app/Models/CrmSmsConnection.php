<?php

namespace HiEvents\Models;

class CrmSmsConnection extends BaseModel
{
    protected function getCastMap(): array
    {
        return [
            'auth_token' => 'encrypted',
            'enabled_countries' => 'array',
        ];
    }
}

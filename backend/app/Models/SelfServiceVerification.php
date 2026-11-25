<?php

namespace HiEvents\Models;

class SelfServiceVerification extends BaseModel
{
    protected function getCastMap(): array
    {
        return [
            'code_expires_at' => 'datetime',
            'session_expires_at' => 'datetime',
        ];
    }
}

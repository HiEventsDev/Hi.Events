<?php

namespace HiEvents\Models;

class CrmContactActivity extends BaseModel
{
    protected function getTimestampsEnabled(): bool
    {
        return false;
    }

    protected function getCastMap(): array
    {
        return [
            'amount' => 'float',
        ];
    }
}

<?php

declare(strict_types=1);

namespace HiEvents\Models;

class AccountFeatureFlagOverride extends BaseModel
{
    protected function getFillableFields(): array
    {
        return [
            'account_id',
            'feature_flag_id',
            'enabled',
        ];
    }

    protected function getCastMap(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}

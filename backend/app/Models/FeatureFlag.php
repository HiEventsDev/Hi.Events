<?php

declare(strict_types=1);

namespace HiEvents\Models;

class FeatureFlag extends BaseModel
{
    protected function getFillableFields(): array
    {
        return [
            'key',
            'enabled_by_default',
        ];
    }

    protected function getCastMap(): array
    {
        return [
            'enabled_by_default' => 'boolean',
        ];
    }
}

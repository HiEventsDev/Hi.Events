<?php

declare(strict_types=1);

namespace HiEvents\Models;

class LifecycleEmailTemplate extends BaseModel
{
    protected function getFillableFields(): array
    {
        return [
            'nudge_type',
            'subject',
            'body',
            'cta_label',
            'enabled_at',
        ];
    }

    protected function getCastMap(): array
    {
        return [
            'enabled_at' => 'datetime',
        ];
    }
}

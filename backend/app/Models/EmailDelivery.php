<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;

class EmailDelivery extends BaseModel
{
    use MassPrunable;

    private const RETENTION_DAYS = 90;

    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }
}

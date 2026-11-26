<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class CrmAutomation extends BaseModel
{
    use SoftDeletes;

    protected function getCastMap(): array
    {
        return [
            'baseline_event_ids' => 'array',
            'config' => 'array',
        ];
    }
}

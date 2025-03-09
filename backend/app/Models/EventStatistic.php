<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class EventStatistic extends BaseModel
{
    use SoftDeletes;

    protected function getCastMap(): array
    {
        return [
            'total_tax' => 'float',
            'total_fee' => 'float',
            'sales_total_before_additions' => 'float',
            'sales_total_gross' => 'float',
            'total_refunded' => 'float',
        ];
    }
}

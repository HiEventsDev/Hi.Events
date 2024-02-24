<?php

namespace HiEvents\Models;

class EventStatistic extends BaseModel
{

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

    protected function getFillableFields(): array
    {
        return [];
    }
}

<?php

namespace TicketKitten\Models;

class EventDailyStatistic extends BaseModel
{

    protected function getCastMap(): array
    {
        return [
            'total_tax' => 'float',
            'total_fee' => 'float',
            'sales_total_gross' => 'float',
            'sales_total_before_additions' => 'float',
        ];
    }

    protected function getFillableFields(): array
    {
        return [];
    }
}

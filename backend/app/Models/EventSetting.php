<?php

namespace TicketKitten\Models;

class EventSetting extends BaseModel
{
    protected function getCastMap(): array
    {
        return [
            'location_details' => 'array',
        ];
    }

    protected function getFillableFields(): array
    {
        return [];
    }
}

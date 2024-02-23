<?php

namespace TicketKitten\Models;

use TicketKitten\Models\Traits\HasImages;

class Organizer extends BaseModel
{
    use HasImages;

    protected function getCastMap(): array
    {
        return [];
    }

    protected function getFillableFields(): array
    {
        return [];
    }
}

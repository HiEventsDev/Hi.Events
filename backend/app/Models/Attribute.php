<?php

declare(strict_types=1);

namespace TicketKitten\Models;

class Attribute extends BaseModel
{
    public function setValueAttribute($value): void
    {
        $this->attributes['value'] = is_array($value) ? json_encode($value) : $value;
    }

    protected function getCastMap(): array
    {
        return [];
    }

    protected function getFillableFields(): array
    {
        return [];
    }
}

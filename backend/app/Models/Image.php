<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;

class Image extends BaseModel
{
    public function entity(): MorphTo
    {
        return $this->morphTo();
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

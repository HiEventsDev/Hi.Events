<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CheckInList extends BaseModel
{
    protected function getCastMap(): array
    {
        return [];
    }

    protected function getFillableFields(): array
    {
        return [];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Product::class,
            table: 'product_check_in_lists',
        );
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

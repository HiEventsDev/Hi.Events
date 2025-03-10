<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CapacityAssignment extends BaseModel
{
    use SoftDeletes;

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Product::class,
            table: 'product_capacity_assignments',
        );
    }
}

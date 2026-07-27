<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductOccurrenceVisibility extends BaseModel
{
    protected $table = 'product_occurrence_visibility';

    public function event_occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected function getTimestampsEnabled(): bool
    {
        return false;
    }
}

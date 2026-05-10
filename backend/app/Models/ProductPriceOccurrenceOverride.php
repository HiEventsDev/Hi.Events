<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceOccurrenceOverride extends BaseModel
{

    protected $table = 'product_price_occurrence_overrides';

    public function event_occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'event_occurrence_id');
    }

    public function product_price(): BelongsTo
    {
        return $this->belongsTo(ProductPrice::class, 'product_price_id');
    }

    protected function getCastMap(): array
    {
        return [
            'price' => 'float',
        ];
    }
}

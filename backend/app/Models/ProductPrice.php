<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends BaseModel
{
    protected function getCastMap(): array
    {
        return [
            'price' => 'float',
        ];
    }

    protected function getFillableFields(): array
    {
        return [];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

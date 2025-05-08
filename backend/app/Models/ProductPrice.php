<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductPrice extends BaseModel
{
    use SoftDeletes;

    protected function getCastMap(): array
    {
        return [
            'price' => 'float',
            'sale_start_date' => 'datetime',
            'sale_end_date' => 'datetime',
            'is_hidden' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

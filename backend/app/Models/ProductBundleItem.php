<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductBundleItem extends BaseModel
{
    protected function getFillableFields(): array
    {
        return [
            'product_bundle_id',
            'product_id',
            'product_price_id',
            'quantity',
        ];
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(ProductBundle::class, 'product_bundle_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

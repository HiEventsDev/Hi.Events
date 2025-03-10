<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends BaseModel
{
    use SoftDeletes;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function getTimestampsEnabled(): bool
    {
        return false;
    }

    protected function getCastMap(): array
    {
        return [
            'total_before_additions' => 'float',
            'price' => 'float',
            'price_before_discount' => 'float',
            'total_before_discount' => 'float',
            'total_tax' => 'float',
            'total_service_fee' => 'float',
            'total_gross' => 'float',
            'taxes_and_fees_rollup' => 'array',
        ];
    }

    protected function getFillableFields(): array
    {
        return [];
    }

    public function product_price(): HasOne
    {
        return $this->hasOne(ProductPrice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

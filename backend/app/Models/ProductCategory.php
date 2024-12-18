<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends BaseModel
{
    protected $table = 'product_categories';

    protected $fillable = [
        'name',
        'no_products_message',
        'description',
        'image',
        'is_hidden',
        'order',
        'event_id',
    ];

    protected function getCastMap(): array
    {
        return [];
    }

    protected function getFillableFields(): array
    {
        return [];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}

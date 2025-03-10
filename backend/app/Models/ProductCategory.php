<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCategory extends BaseModel
{
    use SoftDeletes;

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

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}

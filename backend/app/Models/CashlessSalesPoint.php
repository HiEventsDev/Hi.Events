<?php

declare(strict_types=1);

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashlessSalesPoint extends BaseModel
{
    use SoftDeletes;

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(
            related: Product::class,
            table: 'cashless_sales_point_products',
        );
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}

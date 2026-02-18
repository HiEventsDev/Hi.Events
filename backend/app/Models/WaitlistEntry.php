<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class WaitlistEntry extends BaseModel
{
    use SoftDeletes;

    protected $table = 'waitlist_entries';

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function product_price(): BelongsTo
    {
        return $this->belongsTo(ProductPrice::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

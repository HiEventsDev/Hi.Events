<?php

namespace HiEvents\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RazorpayOrder extends BaseModel
{
    protected $table = 'razorpay_orders';

    protected $fillable = [
        'order_id',
        'razorpay_order_id',
        'razorpay_payment_id',
        'razorpay_signature',
        'method',
        'fee',
        'tax',
        'amount',
        'currency',
        'receipt',
        'status',
    ];

    protected $casts = [
        'amount' => 'integer',
        'fee' => 'integer',
        'tax' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
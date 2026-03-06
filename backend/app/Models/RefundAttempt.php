<?php

namespace HiEvents\Models;

class RefundAttempt extends BaseModel
{
    protected $table = 'refund_attempts';

    protected $fillable = [
        'idempotency_key',
        'payment_id',
        'payment_type',
        'status',
        'request_data',
        'response_data',
        'attempts',
    ];

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'attempts' => 'integer',
    ];
}
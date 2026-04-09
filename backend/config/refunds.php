<?php

return [
    'razorpay' => [
        'idempotency_enabled' => env('RAZORPAY_REFUND_IDEMPOTENCY_ENABLED', true),
    ]
];
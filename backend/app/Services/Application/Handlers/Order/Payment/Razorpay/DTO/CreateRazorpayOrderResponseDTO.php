<?php

namespace HiEvents\Services\Application\Handlers\Order\Payment\Razorpay\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class CreateRazorpayOrderResponseDTO extends BaseDTO
{
    public function __construct(
        public readonly string $razorpay_order_id,
        public readonly string $key_id,
        public readonly int    $amount_minor,
        public readonly string $currency,
        public readonly array  $prefill,
    ) {
    }
}

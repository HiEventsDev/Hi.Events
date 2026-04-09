<?php

namespace HiEvents\Services\Domain\Payment\Razorpay\DTOs;

use HiEvents\DataTransferObjects\BaseDataObject;

class VerifyRazorpayPaymentDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $razorpay_payment_id,
        public readonly string $razorpay_order_id,
        public readonly string $razorpay_signature,
    )
    {
    }
}
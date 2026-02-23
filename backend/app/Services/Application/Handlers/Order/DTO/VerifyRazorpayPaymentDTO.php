<?php

namespace HiEvents\Services\Application\Handlers\Order\DTO;

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
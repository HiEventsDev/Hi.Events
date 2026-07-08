<?php

namespace HiEvents\Services\Domain\Payment\Razorpay\DTOs;

use HiEvents\DataTransferObjects\BaseDataObject;

/**
 * Payload for payment.* events (captured, failed, authorized)
 */
class RazorpayPaymentPayload extends BaseDataObject
{
    public function __construct(
        public readonly RazorpayPaymentDTO $payment,
    ) {}
}
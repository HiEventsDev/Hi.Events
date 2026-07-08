<?php

namespace HiEvents\Services\Domain\Payment\Razorpay\DTOs;

use HiEvents\DataTransferObjects\BaseDataObject;

/**
 * Payload specifically for the order.paid event
 */
class RazorpayOrderPaidPayload extends BaseDataObject
{
    public function __construct(
        public readonly RazorpayOrderDTO $order,
        public readonly RazorpayPaymentDTO $payment,
    ) {}
}
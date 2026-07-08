<?php

namespace HiEvents\Services\Domain\Payment\Razorpay\DTOs;

use HiEvents\DataTransferObjects\BaseDataObject;

class RazorpayOrderPaidEventDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $entity,
        public readonly string $account_id,
        public readonly string $event,
        public readonly RazorpayOrderDTO $order,
        public readonly RazorpayPaymentDTO $payment,
        public readonly int $created_at
    ) {}
}
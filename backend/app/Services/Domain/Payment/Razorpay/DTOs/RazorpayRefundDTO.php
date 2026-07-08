<?php

namespace HiEvents\Services\Domain\Payment\Razorpay\DTOs;

use HiEvents\DataTransferObjects\BaseDataObject;

class RazorpayRefundDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $id,
        public readonly string $entity,
        public readonly int $amount,
        public readonly string $currency,
        public readonly string $payment_id,
        public readonly string $status,
        public readonly ?int $created_at,
        public readonly ?array $notes,
        public readonly ?int $fee,
        public readonly ?int $tax,
    ) {
    }
}
<?php

namespace HiEvents\Services\Domain\Payment\Razorpay\DTOs;

use HiEvents\DataTransferObjects\BaseDataObject;

class RazorpayPaymentDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $id,
        public readonly string $entity,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $status,
        public readonly ?string $order_id,
        public readonly ?string $method,
        public readonly ?int $fee,
        public readonly ?int $tax,
        public readonly ?string $description,
        public readonly ?array $notes,
        public readonly ?string $vpa,
        public readonly ?string $email,
        public readonly ?string $contact,
        public readonly ?int $created_at,
        public readonly ?RazorpayErrorDTO $error, 
    ) {
    }
}
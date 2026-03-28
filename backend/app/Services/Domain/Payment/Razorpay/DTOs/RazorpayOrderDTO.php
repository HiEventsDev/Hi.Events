<?php

namespace HiEvents\Services\Domain\Payment\Razorpay\DTOs;

use HiEvents\DataTransferObjects\BaseDataObject;

class RazorpayOrderDTO extends BaseDataObject
{
    public function __construct(
        public readonly string $id,
        public readonly string $entity,
        public readonly float $amount,
        public readonly float $amount_paid,
        public readonly float $amount_due,
        public readonly string $currency,
        public readonly string $status,
        public readonly ?string $receipt,
        public readonly ?array $notes,
        public readonly int $created_at
    ) {}
}
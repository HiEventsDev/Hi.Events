<?php

namespace HiEvents\Services\Domain\Payment\Razorpay\DTOs;

class CreateRazorpayOrderResponseDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $keyId,
        public readonly int $amount,
        public readonly string $currency,
        public readonly ?string $receipt = null,
    ) {}
}
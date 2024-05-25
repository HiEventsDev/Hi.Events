<?php

namespace HiEvents\Services\Domain\Payment\Stripe\DTOs;

readonly class CreatePaymentIntentResponseDTO
{
    public function __construct(
        public ?string $paymentIntentId = null,
        public ?string $clientSecret = null,
        public ?string $accountId = null,
        public ?string $error = null,
    )
    {
    }
}

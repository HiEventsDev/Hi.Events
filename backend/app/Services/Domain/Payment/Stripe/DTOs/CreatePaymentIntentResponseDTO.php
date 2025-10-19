<?php

namespace HiEvents\Services\Domain\Payment\Stripe\DTOs;

use HiEvents\DomainObjects\Enums\StripePlatform;

readonly class CreatePaymentIntentResponseDTO
{
    public function __construct(
        public ?string         $paymentIntentId = null,
        public ?string         $clientSecret = null,
        public ?string         $accountId = null,
        public ?string         $error = null,
        public int             $applicationFeeAmount = 0,
        public ?StripePlatform $stripePlatform = null,
        public ?string         $publicKey = null,
    )
    {
    }
}

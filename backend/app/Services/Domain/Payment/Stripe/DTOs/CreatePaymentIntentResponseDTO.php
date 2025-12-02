<?php

namespace HiEvents\Services\Domain\Payment\Stripe\DTOs;

use HiEvents\DomainObjects\Enums\StripePlatform;
use HiEvents\Services\Domain\Order\DTO\ApplicationFeeValuesDTO;

readonly class CreatePaymentIntentResponseDTO
{
    public function __construct(
        public ?string                  $paymentIntentId = null,
        public ?string                  $clientSecret = null,
        public ?string                  $accountId = null,
        public ?string                  $error = null,
        public ?ApplicationFeeValuesDTO $applicationFeeData = null,
        public ?StripePlatform          $stripePlatform = null,
        public ?string                  $publicKey = null,
    )
    {
    }
}

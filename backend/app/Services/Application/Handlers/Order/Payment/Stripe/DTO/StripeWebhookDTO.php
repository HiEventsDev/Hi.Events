<?php

namespace HiEvents\Services\Application\Handlers\Order\Payment\Stripe\DTO;

use HiEvents\DataTransferObjects\BaseDTO;

class StripeWebhookDTO extends BaseDTO
{
    public function __construct(
        public readonly string $headerSignature,
        public readonly string $payload,
    )
    {
    }
}

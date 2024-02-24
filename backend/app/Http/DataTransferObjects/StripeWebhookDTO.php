<?php

namespace HiEvents\Http\DataTransferObjects;

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

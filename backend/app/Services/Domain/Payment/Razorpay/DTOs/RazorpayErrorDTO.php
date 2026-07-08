<?php

namespace HiEvents\Services\Domain\Payment\Razorpay\DTOs;

use HiEvents\DataTransferObjects\BaseDataObject;

class RazorpayErrorDTO extends BaseDataObject
{
    public function __construct(
        public readonly ?string $code,
        public readonly ?string $description,
        public readonly ?string $source,
        public readonly ?string $step,
        public readonly ?string $reason,
    ) {
    }
}
<?php

namespace HiEvents\Services\Domain\Payment\Stripe\DTOs;

use HiEvents\DataTransferObjects\BaseDataObject;

class NotCompletableOrderRefundDTO extends BaseDataObject
{
    public function __construct(
        public readonly int $orderId,
        public readonly string $refundId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly ?string $status,
        public readonly string $paymentIntentId,
    ) {}
}

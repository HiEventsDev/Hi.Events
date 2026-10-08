<?php

namespace HiEvents\Exceptions;

use HiEvents\Services\Domain\Payment\Stripe\DTOs\NotCompletableOrderRefundDTO;

class PaymentRefundedException extends CannotAcceptPaymentException
{
    public function __construct(
        string $message,
        public readonly NotCompletableOrderRefundDTO $refund,
    ) {
        parent::__construct($message);
    }
}

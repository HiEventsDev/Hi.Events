<?php

namespace HiEvents\Exceptions;

use HiEvents\DomainObjects\Generated\StripePaymentDomainObjectAbstract;

class OrderNotCompletableException extends BaseException
{
    public function __construct(
        string $message,
        public readonly StripePaymentDomainObjectAbstract $stripePayment,
        public readonly bool $notifyBuyer,
    ) {
        parent::__construct($message);
    }
}

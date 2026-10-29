<?php

namespace HiEvents\Services\Domain\Order\PaymentLink;

use HiEvents\DomainObjects\Enums\OrderCheckoutAccess;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;

class OrderCheckoutAccessService
{
    public function __construct(
        private readonly CheckoutSessionManagementService $sessionManagementService,
        private readonly OrderPaymentLinkTokenService $paymentLinkTokenService,
    ) {}

    public function resolve(OrderDomainObject $order): OrderCheckoutAccess
    {
        if ($order->getSessionId() !== null && $this->sessionManagementService->verifySession($order->getSessionId())) {
            return OrderCheckoutAccess::OWNER;
        }

        if ($this->paymentLinkTokenService->isValidToken($order, $this->sessionManagementService->getSessionId())) {
            return OrderCheckoutAccess::PAYMENT_LINK;
        }

        return OrderCheckoutAccess::NONE;
    }
}

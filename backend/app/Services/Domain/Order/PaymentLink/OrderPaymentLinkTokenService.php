<?php

namespace HiEvents\Services\Domain\Order\PaymentLink;

use Carbon\Carbon;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\Helper\Url;
use Illuminate\Config\Repository;

class OrderPaymentLinkTokenService
{
    public function __construct(
        private readonly Repository $config,
    ) {}

    public function isValidToken(OrderDomainObject $order, ?string $token): bool
    {
        $expected = $this->tokenFor($order);

        return $expected !== null && $token !== null && hash_equals($expected, $token);
    }

    public function urlFor(OrderDomainObject $order): ?string
    {
        $token = $this->tokenFor($order);

        if ($token === null) {
            return null;
        }

        return sprintf(
            Url::getFrontEndUrlFromConfig(
                $order->isOrderReserved() ? Url::ORDER_PAYMENT : Url::ORDER_SUMMARY,
                ['session_identifier' => $token],
            ),
            $order->getEventId(),
            $order->getShortId(),
        );
    }

    private function tokenFor(OrderDomainObject $order): ?string
    {
        if ($order->getPaymentLinkCreatedAt() === null) {
            return null;
        }

        return hash_hmac('sha256', implode('|', [
            'order-payment-link',
            $order->getId(),
            $order->getShortId(),
            Carbon::parse($order->getPaymentLinkCreatedAt(), 'UTC')->getTimestamp(),
        ]), (string) $this->config->get('app.key'));
    }
}

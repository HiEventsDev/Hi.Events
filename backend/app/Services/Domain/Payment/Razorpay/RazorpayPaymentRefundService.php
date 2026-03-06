<?php

namespace HiEvents\Services\Domain\Payment\Razorpay;

use HiEvents\DomainObjects\RazorpayOrderDomainObject;
use HiEvents\Exceptions\RefundNotPossibleException;
use HiEvents\Services\Infrastructure\Razorpay\RazorpayClientFactory;

class RazorpayPaymentRefundService
{
    public function __construct(
        private RazorpayClientFactory $clientFactory
    ) {
    }

    public function refundPayment(
        RazorpayOrderDomainObject $order,
        int $amountInPaise,
        ?string $idempotencyKey = null,
        array $options = []
    ): object {
        $paymentId = $order->getRazorpayPaymentId();
        if (!$paymentId) {
            throw new RefundNotPossibleException(__('No Razorpay payment ID found for this order.'));
        }

        $client = $this->clientFactory->create();

        $params = array_merge(
            [
                'payment_id' => $paymentId,
                'amount' => $amountInPaise,
            ],
            $options
        );

        return $client->refundPayment($params, $idempotencyKey);
    }
}
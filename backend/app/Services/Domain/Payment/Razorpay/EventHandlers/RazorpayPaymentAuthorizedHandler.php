<?php

namespace HiEvents\Services\Domain\Payment\Razorpay\EventHandlers;

use HiEvents\Repository\Interfaces\RazorpayOrdersRepositoryInterface;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\RazorpayPaymentPayload;
use Illuminate\Cache\Repository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Log\Logger;
use Throwable;

class RazorpayPaymentAuthorizedHandler
{
    public function __construct(
        private readonly RazorpayOrdersRepositoryInterface $razorpayOrdersRepository,
        private readonly ConnectionInterface $dbConnection,
        private readonly Logger $logger,
        private readonly Repository $cache,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function handleEvent(RazorpayPaymentPayload $event): void
    {
        $paymentEntity = $event->payment;
        $idempotencyKey = 'razorpay_authorized_' . $paymentEntity->id;

        if ($this->cache->has($idempotencyKey)) {
            $this->logger->info('Razorpay payment.authorized event already handled', [
                'payment_id' => $paymentEntity->id,
            ]);
            return;
        }

        $this->dbConnection->transaction(function () use ($paymentEntity) {
            // Try to find by payment ID first, then by order ID
            $razorpayOrder = $this->razorpayOrdersRepository->findByPaymentId($paymentEntity->id)
                ?? $this->razorpayOrdersRepository->findByRazorpayOrderId($paymentEntity->order_id);

            if (!$razorpayOrder) {
                $this->logger->warning('Razorpay order not found for payment.authorized', [
                    'payment_id' => $paymentEntity->id,
                    'order_id' => $paymentEntity->order_id,
                ]);
                return;
            }

            // Update record with payment details (status will be 'authorized')
            $this->razorpayOrdersRepository->updateByOrderId($razorpayOrder->getOrderId(), [
                'razorpay_payment_id' => $paymentEntity->id,
                'status' => $paymentEntity->status, // 'authorized'
                'method' => $paymentEntity->method,
                'amount' => $paymentEntity->amount,
                'currency' => $paymentEntity->currency,
                'fee' => $paymentEntity->fee,
                'tax' => $paymentEntity->tax,
            ]);

            $this->logger->info('Razorpay payment authorized recorded', [
                'payment_id' => $paymentEntity->id,
                'order_id' => $razorpayOrder->getOrderId(),
            ]);
        });

        $this->cache->put($idempotencyKey, true, now()->addHours(24));
    }
}
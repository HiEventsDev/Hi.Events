<?php

namespace HiEvents\Services\Domain\Payment\Razorpay\EventHandlers;

use HiEvents\Repository\Interfaces\RazorpayOrdersRepositoryInterface;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\RazorpayPaymentPayload;
use Illuminate\Cache\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Log\Logger;
use Throwable;

class RazorpayPaymentFailedHandler
{
    public function __construct(
        private readonly RazorpayOrdersRepositoryInterface $razorpayOrdersRepository,
        private readonly DatabaseManager $databaseManager,
        private readonly Logger $logger,
        private readonly Repository $cache,
    ) {}

    /**
     * @throws Throwable
     */
    public function handleEvent(RazorpayPaymentPayload $event): void
    {
        $paymentEntity = $event->payment;
        $idempotencyKey = 'razorpay_failed_' . $paymentEntity->id;

        if ($this->cache->has($idempotencyKey)) {
            $this->logger->info('Razorpay payment.failed event already handled', [
                'payment_id' => $paymentEntity->id,
            ]);
            return;
        }

        $this->databaseManager->transaction(function () use ($paymentEntity) {
            // Try to find by payment ID first (if this payment ID is already stored)
            // If not found, fallback to order ID (because payment ID might not be stored yet)
            $razorpayOrder = $this->razorpayOrdersRepository->findByPaymentId($paymentEntity->id)
                ?? $this->razorpayOrdersRepository->findByRazorpayOrderId($paymentEntity->order_id);

            if (!$razorpayOrder) {
                $this->logger->warning('Razorpay order not found for payment.failed', [
                    'payment_id' => $paymentEntity->id,
                    'order_id' => $paymentEntity->order_id,
                ]);
                return;
            }

            // Prepare update data including error details if available
            $updateData = [
                'razorpay_payment_id' => $paymentEntity->id,
                'status' => $paymentEntity->status, // 'failed'
                'method' => $paymentEntity->method,
                'amount' => $paymentEntity->amount,
                'currency' => $paymentEntity->currency,
                'fee' => $paymentEntity->fee,
                'tax' => $paymentEntity->tax,
            ];

            // Add error details if present
            if ($paymentEntity->error) {
                $updateData['failure_reason'] = $paymentEntity->error->description;
                $updateData['error_code'] = $paymentEntity->error->code;
            }

            $this->razorpayOrdersRepository->updateByOrderId($razorpayOrder->getOrderId(), $updateData);

            // IMPORTANT: Do NOT change the order's payment status to failed.
            // As per documentation, a failed payment may later be captured.
            // The order remains in its current state (e.g., awaiting payment).

            $this->logger->info('Razorpay payment failed recorded', [
                'payment_id' => $paymentEntity->id,
                'order_id' => $razorpayOrder->getOrderId(),
                'failure_reason' => $paymentEntity->error?->description,
            ]);
        });

        $this->cache->put($idempotencyKey, true, now()->addHours(24));
    }
}
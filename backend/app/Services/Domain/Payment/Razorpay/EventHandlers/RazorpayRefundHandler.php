<?php

namespace HiEvents\Services\Domain\Payment\Razorpay\EventHandlers;

use HiEvents\Services\Domain\Payment\Razorpay\DTOs\RazorpayRefundPayload;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\RazorpayOrdersRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRefundRepositoryInterface;
use Illuminate\Cache\Repository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Log\Logger;
use Throwable;

class RazorpayRefundHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly RazorpayOrdersRepositoryInterface $razorpayOrdersRepository,
        private readonly OrderRefundRepositoryInterface $refundRepository,
        private readonly ConnectionInterface $dbConnection,
        private readonly Logger $logger,
        private readonly Repository $cache,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function handleEvent(RazorpayRefundPayload $payload): void
    {
        $refundEntity = $payload->refund;
        $paymentId = $refundEntity->payment_id;

        // Idempotency key based on refund ID
        $idempotencyKey = 'razorpay_refund_' . $refundEntity->id;

        if ($this->cache->has($idempotencyKey)) {
            $this->logger->info('Razorpay refund event already handled', [
                'refund_id' => $refundEntity->id,
                'payment_id' => $paymentId,
            ]);
            return;
        }

        $this->dbConnection->transaction(function () use ($refundEntity, $paymentId) {
            // 1. Find the Razorpay order record by payment ID
            $razorpayOrder = $this->razorpayOrdersRepository->findByPaymentId($paymentId);

            if (!$razorpayOrder) {
                $this->logger->warning('Razorpay order not found for refund webhook', [
                    'payment_id' => $paymentId,
                    'refund_id' => $refundEntity->id,
                ]);
                return;
            }

            $localOrderId = $razorpayOrder->getOrderId();

            // 2. Load the full order with items
            $order = $this->orderRepository
                ->loadRelation(new Relationship(OrderItemDomainObject::class))
                ->findById($localOrderId);

            if (!$order) {
                $this->logger->warning('Local order not found for refund webhook', [
                    'local_order_id' => $localOrderId,
                    'refund_id' => $refundEntity->id,
                ]);
                return;
            }

            // 3. Store refund details in order_refunds table using repository
            $refundAmountInRupees = $refundEntity->amount / 100; // Convert paise to rupees
            $this->refundRepository->create([
                'order_id' => $order->getId(),
                'payment_provider' => 'razorpay',
                'refund_id' => $refundEntity->id,
                'amount' => $refundAmountInRupees,
                'currency' => $refundEntity->currency,
                'status' => $refundEntity->status,
                'reason' => $refundEntity->notes['reason'] ?? null,
                'metadata' => [
                    'razorpay_refund' => $refundEntity->toArray(),
                ],
            ]);

            // 4. Update order payment status based on total refunded amount
            $this->updateOrderPaymentStatus($order);

            $this->logger->info('Razorpay refund processed successfully', [
                'refund_id' => $refundEntity->id,
                'payment_id' => $paymentId,
                'order_id' => $order->getId(),
                'amount' => $refundAmountInRupees,
                'status' => $refundEntity->status,
            ]);
        });

        // Mark as handled after successful transaction
        $this->cache->put($idempotencyKey, true, now()->addHours(24));
    }

    private function updateOrderPaymentStatus(OrderDomainObject $order): void
    {
        // Get total refunded amount for this order using repository method
        $totalRefunded = $this->refundRepository->getTotalRefundedForOrder($order->getId());
        $orderTotal = $order->getTotalGross(); // Assume returns in rupees

        if ($totalRefunded <= 0) {
            return; // No change if no refunds
        }

        if ($totalRefunded >= $orderTotal) {
            $newStatus = OrderPaymentStatus::REFUNDED->name;
        } else {
            $newStatus = OrderPaymentStatus::PARTIALLY_REFUNDED->name;
        }

        $this->orderRepository->updateFromArray($order->getId(), [
            'refund_status' => $newStatus,
            'total_refunded' => $totalRefunded
        ]);
    }
}
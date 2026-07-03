<?php

namespace HiEvents\Services\Domain\Payment\Razorpay\EventHandlers;

use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\RazorpayOrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Repository\Interfaces\OrderRefundRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\RazorpayOrdersRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;

class RefundProcessedHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface          $orderRepository,
        private readonly OrderRefundRepositoryInterface    $orderRefundRepository,
        private readonly RazorpayOrdersRepositoryInterface $razorpayOrdersRepository,
        private readonly DatabaseManager                   $databaseManager,
        private readonly LoggerInterface                   $logger,
    ) {
    }

    /**
     * Handle a Razorpay `refund.processed` webhook event.
     *
     * @param array $payload The decoded webhook event payload
     * @throws Throwable
     */
    public function handle(array $payload): void
    {
        $refundEntity      = $payload['payload']['refund']['entity'] ?? [];
        $razorpayPaymentId = $refundEntity['payment_id'] ?? null;

        if (!$razorpayPaymentId) {
            $this->logger->error('Razorpay refund.processed webhook missing payment_id', [
                'payload' => $payload,
            ]);
            return;
        }

        $this->databaseManager->transaction(function () use ($refundEntity, $razorpayPaymentId) {
            $razorpayOrder = $this->razorpayOrdersRepository->findFirstWhere([
                RazorpayOrderDomainObjectAbstract::RAZORPAY_PAYMENT_ID => $razorpayPaymentId,
            ]);

            if (!$razorpayOrder) {
                $this->logger->warning('Razorpay order not found for refund.processed event', [
                    'razorpay_payment_id' => $razorpayPaymentId,
                ]);
                return;
            }

            $order = $this->orderRepository->findById($razorpayOrder->getOrderId());
            if (!$order) {
                return;
            }

            // Create refund record
            $amountRefundedMinor = $refundEntity['amount'] ?? 0;
            $amountRefundedFloat = $amountRefundedMinor / 100;
            
            $this->orderRefundRepository->create([
                'order_id'       => $order->getId(),
                'amount_refunded' => $amountRefundedFloat,
                'gateway_transaction_id' => $refundEntity['id'] ?? null,
            ]);

            // Determine if full or partial refund
            $newTotalRefunded = $order->getTotalRefunded() + $amountRefundedFloat;
            $refundStatus     = $newTotalRefunded >= $order->getTotalGross()
                ? OrderPaymentStatus::REFUNDED->name
                : OrderPaymentStatus::PARTIALLY_REFUNDED->name;

            // Update main order
            $updatedOrder = $this->orderRepository->updateFromArray($order->getId(), [
                OrderDomainObjectAbstract::PAYMENT_STATUS => $refundStatus,
                OrderDomainObjectAbstract::TOTAL_REFUNDED => $newTotalRefunded,
            ]);

            event(new OrderStatusChangedEvent($updatedOrder));
            
            $this->logger->info('Razorpay refund processed', [
                'order_id'            => $updatedOrder->getId(),
                'razorpay_payment_id' => $razorpayPaymentId,
                'refund_status'       => $refundStatus,
            ]);
        });
    }
}

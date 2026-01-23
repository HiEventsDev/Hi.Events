<?php

namespace HiEvents\Services\Domain\Payment\Razorpay\EventHandlers;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\Repository\Interfaces\OrderRefundRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\RazorpayOrdersRepositoryInterface;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsRefundService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use HiEvents\Values\MoneyValue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Log\Logger;
use Throwable;

class RazorpayRefundHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface          $orderRepository,
        private readonly RazorpayOrdersRepositoryInterface $razorpayOrdersRepository,
        private readonly Logger                            $logger,
        private readonly DatabaseManager                   $databaseManager,
        private readonly EventStatisticsRefundService      $eventStatisticsRefundService,
        private readonly OrderRefundRepositoryInterface    $orderRefundRepository,
        private readonly DomainEventDispatcherService      $domainEventDispatcherService,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function handleEvent(array $refundData): void
    {
        $this->databaseManager->transaction(function () use ($refundData) {
            // Find Razorpay order by payment_id
            $razorpayOrder = $this->razorpayOrdersRepository->findByPaymentId($refundData['payment_id']);

            if (!$razorpayOrder) {
                $this->logger->warning('Razorpay order not found for refund', [
                    'refund_id' => $refundData['id'],
                    'payment_id' => $refundData['payment_id'],
                ]);
                return;
            }

            $existingRefund = $this->orderRefundRepository->findFirstWhere([
                'refund_id' => $refundData['id'],
            ]);

            if ($existingRefund) {
                $this->logger->info(__('Refund already processed'), [
                    'refund_id' => $refundData['id'],
                    'payment_id' => $refundData['payment_id'],
                ]);
                return;
            }

            // Get order ID from razorpay order array
            $razorpayOrderArray = $razorpayOrder->toArray();
            $orderId = $razorpayOrderArray['order_id'] ?? null;
            
            if (!$orderId) {
                $this->logger->error('Could not get order ID from Razorpay order for refund', [
                    'razorpay_order' => $razorpayOrderArray,
                    'refund_data' => $refundData,
                ]);
                return;
            }

            $order = $this->orderRepository->findById($orderId);

            if ($refundData['status'] !== 'processed') {
                $this->handleFailure($refundData, $order);
                return;
            }

            // Convert from paise to rupees
            $refundedAmount = $refundData['amount'] / 100;

            // Get order ID from order array
            $orderArray = $order->toArray();
            $orderIdFromOrder = $orderArray['id'] ?? $orderId;

            $this->updateOrderRefundedAmount($orderIdFromOrder, $refundedAmount);
            $this->updateOrderStatus($order, $refundedAmount);
            
            // Update event statistics
            $this->updateEventStatistics($order, $refundedAmount, $refundData['currency']);
            
            $this->createOrderRefund($refundData, $order, $refundedAmount, $orderIdFromOrder);

            $this->logger->info(__('Razorpay refund successful'), [
                'order_id' => $orderIdFromOrder,
                'refunded_amount' => $refundedAmount,
                'currency' => $refundData['currency'],
                'refund_id' => $refundData['id'],
            ]);

            $this->domainEventDispatcherService->dispatch(
                new OrderEvent(
                    type: DomainEventType::ORDER_REFUNDED,
                    orderId: $orderIdFromOrder
                ),
            );
        });
    }

    private function updateEventStatistics(OrderDomainObject $order, float $amount, string $currency): void
    {
        // Convert to minor units (paise)
        $amountMinor = $amount * 100;
        $moneyValue = MoneyValue::fromMinorUnit($amountMinor, $currency);
        $this->eventStatisticsRefundService->updateForRefund($order, $moneyValue);
    }

    private function updateOrderRefundedAmount(int $orderId, float $refundedAmount): void
    {
        $this->orderRepository->increment(
            id: $orderId,
            column: OrderDomainObjectAbstract::TOTAL_REFUNDED,
            amount: $refundedAmount
        );
    }

    private function updateOrderStatus(OrderDomainObject $order, float $refundedAmount): void
    {
        // Get order array and extract ID
        $orderArray = $order->toArray();
        $orderId = $orderArray['id'] ?? null;
        
        if (!$orderId) {
            $this->logger->error('Could not get ID from order for status update');
            return;
        }

        // Get total refunded amount from order array
        $totalRefunded = $orderArray['total_refunded'] ?? 0;
        
        // Get total gross from order array
        $totalGross = $orderArray['total_gross'] ?? 0;

        $status = $refundedAmount + $totalRefunded >= $totalGross
            ? OrderRefundStatus::REFUNDED->name
            : OrderRefundStatus::PARTIALLY_REFUNDED->name;

        $this->orderRepository->updateFromArray($orderId, [
            OrderDomainObjectAbstract::REFUND_STATUS => $status,
        ]);
    }

    private function handleFailure(array $refundData, OrderDomainObject $order): void
    {
        // Get order ID from order array
        $orderArray = $order->toArray();
        $orderId = $orderArray['id'] ?? null;
        
        if (!$orderId) {
            $this->logger->error('Could not get ID from order for failure handling');
            return;
        }

        $this->orderRepository->updateFromArray($orderId, [
            OrderDomainObjectAbstract::REFUND_STATUS => OrderRefundStatus::REFUND_FAILED->name,
        ]);

        $this->logger->error(__('Failed to process Razorpay refund'), $refundData);
    }

    private function createOrderRefund(array $refundData, OrderDomainObject $order, float $refundedAmount, int $orderId): void
    {
        $this->orderRefundRepository->create([
            'order_id' => $orderId,
            'payment_provider' => PaymentProviders::RAZORPAY->value,
            'refund_id' => $refundData['id'],
            'amount' => $refundedAmount,
            'currency' => $refundData['currency'],
            'status' => $refundData['status'],
            'metadata' => $refundData,
        ]);
    }
}
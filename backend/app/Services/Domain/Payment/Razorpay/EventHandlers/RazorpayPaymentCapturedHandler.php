<?php

namespace HiEvents\Services\Domain\Payment\Razorpay\EventHandlers;

use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\RazorpayOrdersRepositoryInterface;
use HiEvents\Services\Domain\Order\OrderApplicationFeeService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use Illuminate\Cache\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Log\Logger;
use Throwable;

class RazorpayPaymentCapturedHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface          $orderRepository,
        private readonly RazorpayOrdersRepositoryInterface $razorpayOrdersRepository,
        private readonly AffiliateRepositoryInterface      $affiliateRepository,
        private readonly ProductQuantityUpdateService      $quantityUpdateService,
        private readonly AttendeeRepositoryInterface       $attendeeRepository,
        private readonly DatabaseManager                   $databaseManager,
        private readonly Logger                            $logger,
        private readonly Repository                        $cache,
        private readonly DomainEventDispatcherService      $domainEventDispatcherService,
        private readonly OrderApplicationFeeService        $orderApplicationFeeService,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function handleEvent(array $paymentData): void
    {
        if ($this->isPaymentAlreadyHandled($paymentData['id'])) {
            $this->logger->info('Razorpay payment already handled via webhook', [
                'razorpay_payment_id' => $paymentData['id'],
            ]);
            return;
        }

        $this->databaseManager->transaction(function () use ($paymentData) {
            // Find by razorpay_payment_id (stored in razorpay_order table)
            $razorpayOrder = $this->razorpayOrdersRepository->findByPaymentId($paymentData['id']);

            if (!$razorpayOrder) {
                $this->logger->warning('Razorpay order not found for webhook', [
                    'razorpay_payment_id' => $paymentData['id'],
                ]);
                return;
            }

            // Get the order ID from the razorpay order object
            // Try toArray() method first, then check for getters
            $razorpayOrderArray = $razorpayOrder->toArray();
            $orderId = $razorpayOrderArray['order_id'] ?? null;
            
            if (!$orderId) {
                $this->logger->error('Could not get order ID from Razorpay order', [
                    'razorpay_order' => $razorpayOrderArray,
                ]);
                return;
            }

            // Load the order with items
            $order = $this->orderRepository
                ->loadRelation(new Relationship(OrderItemDomainObject::class))
                ->findById($orderId);

            if (!$order) {
                $this->logger->warning('Order not found for Razorpay payment', [
                    'razorpay_payment_id' => $paymentData['id'],
                    'order_id' => $orderId,
                ]);
                return;
            }

            // Update Razorpay order info with webhook data
            $this->razorpayOrdersRepository->updateByOrderId($orderId, [
                'razorpay_payment_id' => $paymentData['id'],
                'status' => $paymentData['status'],
                'method' => $paymentData['method'],
                'amount' => $paymentData['amount'] / 100, // Convert from paise to rupees
                'currency' => $paymentData['currency'],
                'fee' => $paymentData['fee'] ?? 0,
                'tax' => $paymentData['tax'] ?? 0,
            ]);

            // Update order if not already completed (in case callback failed)
            // Get payment status from order array
            $orderArray = $order->toArray();
            $paymentStatus = $orderArray['payment_status'] ?? null;
            
            if ($paymentStatus !== OrderPaymentStatus::PAYMENT_RECEIVED->name) {
                $updatedOrder = $this->updateOrderStatuses($order);
                
                $this->updateAttendeeStatuses($updatedOrder);
                $this->quantityUpdateService->updateQuantitiesFromOrder($updatedOrder);
                $this->updateAffiliateSales($updatedOrder);
                
                OrderStatusChangedEvent::dispatch($updatedOrder);
                
                $this->domainEventDispatcherService->dispatch(
                    new OrderEvent(
                        type: DomainEventType::ORDER_CREATED,
                        orderId: $updatedOrder->getId()
                    )
                );
            }

            // Store application fee
            $this->storeApplicationFeePayment($order, $paymentData);
            
            $this->markPaymentAsHandled($paymentData['id'], $order);
        });
    }

    private function updateOrderStatuses(OrderDomainObject $order): OrderDomainObject
    {
        return $this->orderRepository
            ->updateFromArray($order->getId(), [
                OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::PAYMENT_RECEIVED->name,
                OrderDomainObjectAbstract::STATUS => OrderStatus::COMPLETED->name,
                OrderDomainObjectAbstract::PAYMENT_PROVIDER => PaymentProviders::RAZORPAY->value,
            ]);
    }

    private function updateAttendeeStatuses(OrderDomainObject $order): void
    {
        $this->attendeeRepository->updateWhere(
            attributes: [
                'status' => AttendeeStatus::ACTIVE->name,
            ],
            where: [
                'order_id' => $order->getId(),
                'status' => AttendeeStatus::AWAITING_PAYMENT->name,
            ],
        );
    }

    private function updateAffiliateSales(OrderDomainObject $order): void
    {
        // Get affiliate ID from order array
        $orderArray = $order->toArray();
        $affiliateId = $orderArray['affiliate_id'] ?? null;
        
        if ($affiliateId) {
            $this->affiliateRepository->incrementSales(
                affiliateId: $affiliateId,
                amount: $order->getTotalGross()
            );
        }
    }

    private function storeApplicationFeePayment(OrderDomainObject $order, array $paymentData): void
    {
        $feeAmount = $paymentData['fee'] ?? 0; // Fee in paise
        
        $this->orderApplicationFeeService->createOrderApplicationFee(
            orderId: $order->getId(),
            applicationFeeAmountMinorUnit: $feeAmount,
            orderApplicationFeeStatus: \HiEvents\DomainObjects\Status\OrderApplicationFeeStatus::PAID,
            paymentMethod: PaymentProviders::RAZORPAY,
            currency: $order->getCurrency(),
        );
    }

    private function isPaymentAlreadyHandled(string $paymentId): bool
    {
        return $this->cache->has('razorpay_webhook_payment_' . $paymentId);
    }

    private function markPaymentAsHandled(string $paymentId, OrderDomainObject $order): void
    {
        $this->logger->info('Razorpay payment captured via webhook', [
            'razorpay_payment_id' => $paymentId,
            'order_id' => $order->getId(),
            'amount' => $order->getTotalGross(),
            'currency' => $order->getCurrency(),
        ]);

        $this->cache->put('razorpay_webhook_payment_' . $paymentId, true, now()->addHours(24));
    }
}
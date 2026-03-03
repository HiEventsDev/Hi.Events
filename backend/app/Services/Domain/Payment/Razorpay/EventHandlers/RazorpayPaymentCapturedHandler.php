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
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\RazorpayPaymentPayload;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use Illuminate\Cache\Repository;
use Illuminate\Database\ConnectionInterface;
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
        private readonly ConnectionInterface               $dbConnection,
        private readonly Logger                            $logger,
        private readonly Repository                        $cache,
        private readonly DomainEventDispatcherService      $domainEventDispatcherService,
        private readonly OrderApplicationFeeService        $orderApplicationFeeService,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function handleEvent(RazorpayPaymentPayload $event): void
    {
        $paymentEntity = $event->payment;

        // Idempotency check: avoid processing the same payment twice
        if ($this->isPaymentAlreadyHandled($paymentEntity->id)) {
            $this->logger->info('Razorpay payment already handled via webhook', [
                'razorpay_payment_id' => $paymentEntity->id,
            ]);
            return;
        }

        $this->dbConnection->transaction(function () use ($paymentEntity) {
            // Find the local razorpay order record by the Razorpay payment ID
            $razorpayOrder = $this->razorpayOrdersRepository->findByPaymentId($paymentEntity->id);

            if (!$razorpayOrder) {
                $this->logger->warning('Razorpay order not found for webhook', [
                    'razorpay_payment_id' => $paymentEntity->id,
                ]);
                return;
            }

            // Extract the local order ID from the razorpay order record
            $razorpayOrderArray = $razorpayOrder->toArray();
            $orderId = $razorpayOrderArray['order_id'] ?? null;

            if (!$orderId) {
                $this->logger->error('Could not get order ID from Razorpay order', [
                    'razorpay_order' => $razorpayOrderArray,
                ]);
                return;
            }

            // Load the full order with its items
            $order = $this->orderRepository
                ->loadRelation(new Relationship(OrderItemDomainObject::class))
                ->findById($orderId);

            if (!$order) {
                $this->logger->warning('Order not found for Razorpay payment', [
                    'razorpay_payment_id' => $paymentEntity->id,
                    'order_id' => $orderId,
                ]);
                return;
            }

            // Update the razorpay_orders record with webhook data (all amounts in paise)
            $this->razorpayOrdersRepository->updateByOrderId($orderId, [
                'razorpay_payment_id' => $paymentEntity->id,
                'status'  => $paymentEntity->status,
                'method'  => $paymentEntity->method,
                'amount'  => $paymentEntity->amount,
                'currency' => $paymentEntity->currency,
                'fee'     => $paymentEntity->fee,
                'tax'     => $paymentEntity->tax,
            ]);

            // If the order is not already marked as paid, update its status and related entities
            $orderArray = $order->toArray();
            $currentPaymentStatus = $orderArray['payment_status'] ?? null;

            if ($currentPaymentStatus !== OrderPaymentStatus::PAYMENT_RECEIVED->name) {
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

            // Store the application fee (fee is already in paise)
            $this->orderApplicationFeeService->createOrderApplicationFee(
                orderId: $order->getId(),
                applicationFeeAmountMinorUnit: $paymentEntity->fee ?? 0,
                orderApplicationFeeStatus: \HiEvents\DomainObjects\Status\OrderApplicationFeeStatus::PAID,
                paymentMethod: PaymentProviders::RAZORPAY,
                currency: $paymentEntity->currency,
            );

            // Final idempotency marker
            $this->markPaymentAsHandled($paymentEntity->id, $order);
        });
    }

    private function updateOrderStatuses(OrderDomainObject $order): OrderDomainObject
    {
        return $this->orderRepository
            ->updateFromArray($order->getId(), [
                OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::PAYMENT_RECEIVED->name,
                OrderDomainObjectAbstract::STATUS         => OrderStatus::COMPLETED->name,
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
                'status'   => AttendeeStatus::AWAITING_PAYMENT->name,
            ],
        );
    }

    private function updateAffiliateSales(OrderDomainObject $order): void
    {
        $orderArray = $order->toArray();
        $affiliateId = $orderArray['affiliate_id'] ?? null;

        if ($affiliateId) {
            $this->affiliateRepository->incrementSales(
                affiliateId: $affiliateId,
                amount: $order->getTotalGross()
            );
        }
    }

    private function isPaymentAlreadyHandled(string $paymentId): bool
    {
        return $this->cache->has('razorpay_webhook_payment_' . $paymentId);
    }

    private function markPaymentAsHandled(string $paymentId, OrderDomainObject $order): void
    {
        $this->logger->info('Razorpay payment captured via webhook', [
            'razorpay_payment_id' => $paymentId,
            'order_id'            => $order->getId(),
            'amount'              => $order->getTotalGross(),
            'currency'            => $order->getCurrency(),
        ]);

        $this->cache->put('razorpay_webhook_payment_' . $paymentId, true, now()->addHours(24));
    }
}
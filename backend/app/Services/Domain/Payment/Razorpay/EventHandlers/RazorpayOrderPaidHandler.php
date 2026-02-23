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
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\RazorpayOrderPaidEventDTO;
use HiEvents\Services\Domain\Payment\Razorpay\DTOs\RazorpayOrderPaidPayload;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use Illuminate\Cache\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Log\Logger;
use Throwable;

class RazorpayOrderPaidHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly RazorpayOrdersRepositoryInterface $razorpayOrdersRepository,
        private readonly AffiliateRepositoryInterface $affiliateRepository,
        private readonly ProductQuantityUpdateService $quantityUpdateService,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly DatabaseManager $databaseManager,
        private readonly Logger $logger,
        private readonly Repository $cache,
        private readonly DomainEventDispatcherService $domainEventDispatcherService,
        private readonly OrderApplicationFeeService $orderApplicationFeeService,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function handleEvent(RazorpayOrderPaidPayload $event): void
    {
        $orderEntity = $event->order;
        $paymentEntity = $event->payment;

        // Use the Razorpay order ID as the idempotency key (or payment ID)
        $idempotencyKey = 'razorpay_order_paid_' . $orderEntity->id;

        if ($this->cache->has($idempotencyKey)) {
            $this->logger->info('Razorpay order.paid event already handled', [
                'razorpay_order_id' => $orderEntity->id,
                'razorpay_payment_id' => $paymentEntity->id,
            ]);
            return;
        }

        $this->databaseManager->transaction(function () use ($orderEntity, $paymentEntity) {
            // Find local razorpay order record by the Razorpay order ID
            $razorpayOrder = $this->razorpayOrdersRepository->findByRazorpayOrderId($orderEntity->id);

            if (!$razorpayOrder) {
                $this->logger->warning('Razorpay order not found for order.paid webhook', [
                    'razorpay_order_id' => $orderEntity->id,
                ]);
                return;
            }

            $localOrderId = $razorpayOrder->getOrderId();

            // Load the full local order with items
            $order = $this->orderRepository
                ->loadRelation(new Relationship(OrderItemDomainObject::class))
                ->findById($localOrderId);

            if (!$order) {
                $this->logger->warning('Local order not found for order.paid webhook', [
                    'local_order_id' => $localOrderId,
                    'razorpay_order_id' => $orderEntity->id,
                ]);
                return;
            }

            // Update the razorpay_orders record with payment details (all amounts in paise)
            $this->razorpayOrdersRepository->updateByOrderId($localOrderId, [
                'razorpay_payment_id' => $paymentEntity->id,
                'status' => $paymentEntity->status,
                'method' => $paymentEntity->method,
                'amount' => $paymentEntity->amount,
                'currency' => $paymentEntity->currency,
                'fee' => $paymentEntity->fee,
                'tax' => $paymentEntity->tax,
            ]);

            // If order not already marked as paid, update its status and related entities
            if ($order->getPaymentStatus() !== OrderPaymentStatus::PAYMENT_RECEIVED->name) {
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

            // Store application fee (fee is in paise)
            $this->orderApplicationFeeService->createOrderApplicationFee(
                orderId: $order->getId(),
                applicationFeeAmountMinorUnit: $paymentEntity->fee ?? 0,
                orderApplicationFeeStatus: \HiEvents\DomainObjects\Status\OrderApplicationFeeStatus::PAID,
                paymentMethod: PaymentProviders::RAZORPAY,
                currency: $paymentEntity->currency,
            );

            $this->logger->info('Razorpay order.paid webhook processed successfully', [
                'razorpay_order_id' => $orderEntity->id,
                'razorpay_payment_id' => $paymentEntity->id,
                'local_order_id' => $order->getId(),
            ]);
        });

        // Mark as handled after successful transaction
        $this->cache->put($idempotencyKey, true, now()->addHours(24));
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
        $orderArray = $order->toArray();
        $affiliateId = $orderArray['affiliate_id'] ?? null;

        if ($affiliateId) {
            $this->affiliateRepository->incrementSales(
                affiliateId: $affiliateId,
                amount: $order->getTotalGross()
            );
        }
    }
}
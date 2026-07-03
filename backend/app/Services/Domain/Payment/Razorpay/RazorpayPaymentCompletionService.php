<?php

namespace HiEvents\Services\Domain\Payment\Razorpay;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\RazorpayOrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\RazorpayOrderDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderApplicationFeeStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Exceptions\CannotAcceptPaymentException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\RazorpayOrdersRepositoryInterface;
use HiEvents\Services\Domain\Order\OrderApplicationFeeService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use Illuminate\Cache\Repository;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;

class RazorpayPaymentCompletionService
{
    public function __construct(
        private readonly OrderRepositoryInterface          $orderRepository,
        private readonly RazorpayOrdersRepositoryInterface $razorpayOrdersRepository,
        private readonly AttendeeRepositoryInterface       $attendeeRepository,
        private readonly ProductQuantityUpdateService      $quantityUpdateService,
        private readonly DomainEventDispatcherService      $domainEventDispatcherService,
        private readonly OrderApplicationFeeService        $orderApplicationFeeService,
        private readonly EventSettingsRepositoryInterface  $eventSettingsRepository,
        private readonly DatabaseManager                   $databaseManager,
        private readonly LoggerInterface                   $logger,
        private readonly Repository                        $cache,
    ) {
    }

    /**
     * Fulfil the order upon a successful Razorpay payment.
     *
     * @throws Throwable
     */
    public function completePayment(
        RazorpayOrderDomainObject $razorpayOrder,
        string                    $razorpayPaymentId,
        string                    $razorpaySignature,
    ): void {
        $cacheKey = 'razorpay_payment_handled_' . $razorpayPaymentId;

        if ($this->cache->has($cacheKey)) {
            $this->logger->info('Razorpay payment already handled', [
                'razorpay_payment_id' => $razorpayPaymentId,
            ]);
            return;
        }

        $this->databaseManager->transaction(function () use ($razorpayOrder, $razorpayPaymentId, $razorpaySignature, $cacheKey) {
            $order = $this->orderRepository
                ->loadRelation(OrderItemDomainObject::class)
                ->findById($razorpayOrder->getOrderId());

            if (!$order) {
                $this->logger->error('Order not found for Razorpay payment', [
                    'razorpay_order_id'   => $razorpayOrder->getRazorpayOrderId(),
                    'razorpay_payment_id' => $razorpayPaymentId,
                ]);
                return;
            }

            // Guard: only process if order is awaiting payment or payment failed
            if (!in_array($order->getPaymentStatus(), [
                OrderPaymentStatus::AWAITING_PAYMENT->name,
                OrderPaymentStatus::PAYMENT_FAILED->name,
            ], true)) {
                throw new CannotAcceptPaymentException(
                    __('Order is not awaiting payment. Order: :id', ['id' => $order->getId()])
                );
            }

            // Guard: check order has not expired
            if ($order->getReservedUntil() && (new Carbon($order->getReservedUntil()))->isPast()) {
                throw new CannotAcceptPaymentException(
                    __('Order has expired. Order: :id', ['id' => $order->getId()])
                );
            }

            // Update the razorpay_orders record
            $this->razorpayOrdersRepository->updateWhere(
                attributes: [
                    RazorpayOrderDomainObjectAbstract::RAZORPAY_PAYMENT_ID => $razorpayPaymentId,
                    RazorpayOrderDomainObjectAbstract::RAZORPAY_SIGNATURE   => $razorpaySignature,
                    RazorpayOrderDomainObjectAbstract::STATUS               => 'paid',
                ],
                where: [
                    RazorpayOrderDomainObjectAbstract::RAZORPAY_ORDER_ID => $razorpayOrder->getRazorpayOrderId(),
                ]
            );

            $updatedOrder = $this->updateOrderStatuses($order);
            $this->updateAttendeeStatuses($updatedOrder);
            $this->quantityUpdateService->updateQuantitiesFromOrder($updatedOrder);

            event(new OrderStatusChangedEvent($updatedOrder));

            $this->domainEventDispatcherService->dispatch(
                new OrderEvent(
                    type: DomainEventType::ORDER_CREATED,
                    orderId: $updatedOrder->getId()
                ),
            );

            $this->orderApplicationFeeService->createOrderApplicationFee(
                orderId: $updatedOrder->getId(),
                applicationFeeAmountMinorUnit: 0,
                orderApplicationFeeStatus: OrderApplicationFeeStatus::PAID,
                paymentMethod: PaymentProviders::RAZORPAY,
                currency: $updatedOrder->getCurrency(),
            );

            $this->cache->put($cacheKey, true, 3600);

            $this->logger->info('Razorpay payment completed successfully', [
                'order_id'            => $updatedOrder->getId(),
                'razorpay_payment_id' => $razorpayPaymentId,
            ]);
        });
    }

    private function updateOrderStatuses(OrderDomainObject $order): OrderDomainObject
    {
        return $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->updateFromArray($order->getId(), [
                OrderDomainObjectAbstract::PAYMENT_STATUS  => OrderPaymentStatus::PAYMENT_RECEIVED->name,
                OrderDomainObjectAbstract::STATUS          => OrderStatus::COMPLETED->name,
                OrderDomainObjectAbstract::PAYMENT_PROVIDER => PaymentProviders::RAZORPAY->value,
            ]);
    }

    private function updateAttendeeStatuses(OrderDomainObject $updatedOrder): void
    {
        $this->attendeeRepository->updateWhere(
            attributes: ['status' => AttendeeStatus::ACTIVE->name],
            where: [
                'order_id' => $updatedOrder->getId(),
                'status'   => AttendeeStatus::AWAITING_PAYMENT->name,
            ],
        );
    }
}

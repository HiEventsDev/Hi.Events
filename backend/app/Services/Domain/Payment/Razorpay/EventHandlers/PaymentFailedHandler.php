<?php

namespace HiEvents\Services\Domain\Payment\Razorpay\EventHandlers;

use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\RazorpayOrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\RazorpayOrdersRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;

class PaymentFailedHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface          $orderRepository,
        private readonly RazorpayOrdersRepositoryInterface $razorpayOrdersRepository,
        private readonly DatabaseManager                   $databaseManager,
        private readonly LoggerInterface                   $logger,
    ) {
    }

    /**
     * Handle a Razorpay `payment.failed` webhook event.
     *
     * @param array $payload The decoded webhook event payload
     * @throws Throwable
     */
    public function handle(array $payload): void
    {
        $paymentEntity   = $payload['payload']['payment']['entity'] ?? [];
        $razorpayOrderId = $paymentEntity['order_id'] ?? null;

        if (!$razorpayOrderId) {
            $this->logger->error('Razorpay payment.failed webhook missing order_id', [
                'payload' => $payload,
            ]);
            return;
        }

        $this->databaseManager->transaction(function () use ($razorpayOrderId) {
            $razorpayOrder = $this->razorpayOrdersRepository->findFirstWhere([
                RazorpayOrderDomainObjectAbstract::RAZORPAY_ORDER_ID => $razorpayOrderId,
            ]);

            if (!$razorpayOrder) {
                $this->logger->warning('Razorpay order not found for payment.failed event', [
                    'razorpay_order_id' => $razorpayOrderId,
                ]);
                return;
            }

            // Mark the razorpay_order as failed
            $this->razorpayOrdersRepository->updateWhere(
                attributes: [RazorpayOrderDomainObjectAbstract::STATUS => 'failed'],
                where: [RazorpayOrderDomainObjectAbstract::ID => $razorpayOrder->getId()],
            );

            // Update the main order to PAYMENT_FAILED
            $updatedOrder = $this->orderRepository
                ->loadRelation(OrderItemDomainObject::class)
                ->updateFromArray($razorpayOrder->getOrderId(), [
                    OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::PAYMENT_FAILED->name,
                ]);

            event(new OrderStatusChangedEvent($updatedOrder));
            
            $this->logger->info('Razorpay payment failed processed', [
                'order_id'          => $updatedOrder->getId(),
                'razorpay_order_id' => $razorpayOrderId,
            ]);
        });
    }
}

<?php

namespace HiEvents\Services\Domain\Payment\Stripe\EventHandlers;

use Brick\Money\Money;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\Mail\Order\OrderRefunded;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRefundRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\StripePaymentsRepositoryInterface;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsRefundService;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentIntentRefundService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use HiEvents\Values\MoneyValue;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;
use Illuminate\Log\Logger;
use Stripe\Refund;
use Throwable;

class ChargeRefundUpdatedHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly StripePaymentsRepositoryInterface $stripePaymentsRepository,
        private readonly Logger $logger,
        private readonly DatabaseManager $databaseManager,
        private readonly EventStatisticsRefundService $eventStatisticsRefundService,
        private readonly OrderRefundRepositoryInterface $orderRefundRepository,
        private readonly DomainEventDispatcherService $domainEventDispatcherService,
        private readonly TransactionLockService $transactionLockService,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly Mailer $mailer,
    ) {}

    /**
     * @throws Throwable
     */
    public function handleEvent(Refund $refund): void
    {
        $this->databaseManager->transaction(function () use ($refund) {
            $stripePayment = $this->stripePaymentsRepository->findFirstWhere([
                'payment_intent_id' => $refund->payment_intent,
            ]);

            if (! $stripePayment) {
                return;
            }

            $this->lockOrder($stripePayment->getOrderId());

            $existingRefund = $this->orderRefundRepository->findFirstWhere([
                'refund_id' => $refund->id,
            ]);

            if ($existingRefund) {
                $this->logger->info(__('Refund already processed'), [
                    'refund_id' => $refund->id,
                    'payment_intent_id' => $refund->payment_intent,
                    'existing_refund' => $existingRefund->toArray(),
                ]);

                return;
            }

            if ($this->isStillProcessing($refund)) {
                $this->logger->info('Stripe refund is still processing', [
                    'refund_id' => $refund->id,
                    'payment_intent_id' => $refund->payment_intent,
                    'status' => $refund->status,
                    'pending_reason' => $refund->pending_reason ?? null,
                ]);

                return;
            }

            $order = $this->orderRepository->findById($stripePayment->getOrderId());

            if ($refund->status !== Refund::STATUS_SUCCEEDED) {
                $this->handleFailure($refund, $order);

                return;
            }

            $refundedAmount = $this->amountAsFloat($refund->amount, $order->getCurrency());
            $refundedMoney = MoneyValue::fromMinorUnit($refund->amount, $order->getCurrency());

            $this->updateOrderRefundedAmount($order->getId(), $refundedAmount);
            $this->updateOrderStatus($order, $refundedAmount);
            $this->updateEventStatistics($order, $refundedMoney);
            $this->createOrderRefund($refund, $order, $refundedAmount);

            if ($this->wasBuyerNotificationRequested($refund) && $order->getEmail() !== null) {
                $this->notifyBuyer($order, $refundedMoney);
            }

            $this->logger->info(__('Stripe refund successful'), [
                'order_id' => $order->getId(),
                'refunded_amount' => $refundedAmount,
                'currency' => $order->getCurrency(),
                'refund_id' => $refund->id,
            ]);

            $this->domainEventDispatcherService->dispatch(
                new OrderEvent(
                    type: DomainEventType::ORDER_REFUNDED,
                    orderId: $order->getId()
                ),
            );
        });
    }

    private function lockOrder(int $orderId): void
    {
        $this->transactionLockService->lockOrder($this->orderRepository->findById($orderId)->getShortId());
    }

    private function isStillProcessing(Refund $refund): bool
    {
        return in_array($refund->status, [Refund::STATUS_PENDING, Refund::STATUS_REQUIRES_ACTION], true);
    }

    private function wasBuyerNotificationRequested(Refund $refund): bool
    {
        return ($refund->metadata[StripePaymentIntentRefundService::NOTIFY_BUYER_METADATA_KEY] ?? null) === '1';
    }

    private function notifyBuyer(OrderDomainObject $order, MoneyValue $refundAmount): void
    {
        $event = $this->eventRepository
            ->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
            ->loadRelation(EventSettingDomainObject::class)
            ->findById($order->getEventId());

        $this->mailer
            ->to($order->getEmail())
            ->locale($order->getLocale())
            ->send(new OrderRefunded(
                order: $order,
                event: $event,
                organizer: $event->getOrganizer(),
                eventSettings: $event->getEventSettings(),
                refundAmount: $refundAmount,
            ));
    }

    private function amountAsFloat(int $amount, string $currency): float
    {
        return Money::ofMinor($amount, $currency)->getAmount()->toFloat();
    }

    private function updateEventStatistics(OrderDomainObject $order, MoneyValue $amount): void
    {
        $this->eventStatisticsRefundService->updateForRefund($order, $amount);
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
        $status = $refundedAmount + $order->getTotalRefunded() >= $order->getTotalGross()
            ? OrderRefundStatus::REFUNDED->name
            : OrderRefundStatus::PARTIALLY_REFUNDED->name;

        $this->orderRepository->updateFromArray($order->getId(), [
            OrderDomainObjectAbstract::REFUND_STATUS => $status,
        ]);
    }

    private function handleFailure(Refund $refund, OrderDomainObject $order): void
    {
        $this->orderRepository->updateFromArray($order->getId(), [
            OrderDomainObjectAbstract::REFUND_STATUS => OrderRefundStatus::REFUND_FAILED->name,
        ]);

        $this->createOrderRefund($refund, $order, $this->amountAsFloat($refund->amount, $order->getCurrency()));

        $this->logger->error(__('Failed to refund stripe charge'), $refund->toArray());
    }

    private function createOrderRefund(Refund $refund, OrderDomainObject $order, float $amount): void
    {
        $this->orderRefundRepository->create([
            'order_id' => $order->getId(),
            'payment_provider' => PaymentProviders::STRIPE->value,
            'refund_id' => $refund->id,
            'amount' => $amount,
            'currency' => $order->getCurrency(),
            'status' => $refund->status,
            'metadata' => array_merge($refund->metadata?->toArray() ?? [], [
                'payment_intent' => $refund->payment_intent,
            ]),
        ]);
    }
}

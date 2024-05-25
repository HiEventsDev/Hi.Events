<?php

namespace HiEvents\Services\Domain\Payment\Stripe\EventHandlers;

use Brick\Money\Money;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\StripePaymentsRepositoryInterface;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsUpdateService;
use HiEvents\Values\MoneyValue;
use Illuminate\Database\DatabaseManager;
use Illuminate\Log\Logger;
use Stripe\Refund;
use Throwable;

readonly class ChargeRefundUpdatedHandler
{
    public function __construct(
        private OrderRepositoryInterface          $orderRepository,
        private StripePaymentsRepositoryInterface $stripePaymentsRepository,
        private Logger                            $logger,
        private DatabaseManager                   $databaseManager,
        private EventStatisticsUpdateService      $eventStatisticsUpdateService,

    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handleEvent(Refund $refund): void
    {
        $this->databaseManager->transaction(function () use ($refund) {
            $stripePayment = $this->stripePaymentsRepository->findFirstWhere([
                'payment_intent_id' => $refund->payment_intent
            ]);

            if (!$stripePayment) {
                return;
            }

            $order = $this->orderRepository->findById($stripePayment->getOrderId());

            if ($refund->status !== 'succeeded') {
                $this->handleFailure($refund, $order);
                return;
            }

            $refundedAmount = $this->amountAsFloat($refund->amount, $order->getCurrency());

            $this->updateOrderRefundedAmount($order->getId(), $refundedAmount);
            $this->updateOrderStatus($order, $refundedAmount);
            $this->updateEventStatistics($order, MoneyValue::fromMinorUnit($refund->amount, $order->getCurrency()));
        });
    }

    private function amountAsFloat(int $amount, string $currency): float
    {
        return Money::ofMinor($amount, $currency)->getAmount()->toFloat();
    }

    private function updateEventStatistics(OrderDomainObject $order, MoneyValue $amount): void
    {
        $this->eventStatisticsUpdateService->updateEventStatsTotalRefunded($order, $amount);
    }

    private function updateOrderRefundedAmount(int $orderId, float $refundedAmount): void
    {
        $this->orderRepository->increment(
            $orderId,
            OrderDomainObjectAbstract::TOTAL_REFUNDED,
            $refundedAmount
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

        $this->logger->error(__('Failed to refund stripe charge'), $refund->toArray());
    }
}

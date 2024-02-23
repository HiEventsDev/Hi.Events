<?php

namespace TicketKitten\Service\Handler\Order\Payment\Stripe;

use Brick\Math\Exception\MathException;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Exception\UnknownCurrencyException;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;
use TicketKitten\DomainObjects\Generated\OrderDomainObjectAbstract;
use TicketKitten\DomainObjects\OrderDomainObject;
use TicketKitten\DomainObjects\Status\OrderRefundStatus;
use TicketKitten\DomainObjects\StripePaymentDomainObject;
use TicketKitten\Exceptions\RefundNotPossibleException;
use TicketKitten\Http\DataTransferObjects\RefundOrderDTO;
use TicketKitten\Mail\OrderRefunded;
use TicketKitten\Repository\Eloquent\Value\Relationship;
use TicketKitten\Repository\Interfaces\EventRepositoryInterface;
use TicketKitten\Repository\Interfaces\OrderRepositoryInterface;
use TicketKitten\Service\Common\EventStatistics\EventStatisticsUpdateService;
use TicketKitten\Service\Common\Order\OrderCancelService;
use TicketKitten\Service\Common\Payment\Stripe\StripePaymentIntentRefundService;
use TicketKitten\ValuesObjects\MoneyValue;

readonly class RefundOrderHandler
{
    public function __construct(
        private StripePaymentIntentRefundService $refundService,
        private OrderRepositoryInterface         $orderRepository,
        private EventRepositoryInterface         $eventRepository,
        private Mailer                           $mailer,
        private OrderCancelService               $orderCancelService,
        private DatabaseManager                  $databaseManager,
        private EventStatisticsUpdateService     $eventStatisticsUpdateService,
    )
    {
    }

    /**
     * @throws RefundNotPossibleException
     * @throws ApiErrorException
     * @throws Throwable
     */
    public function handle(RefundOrderDTO $refundOrderDTO): OrderDomainObject
    {
        return $this->databaseManager->transaction(fn() => $this->refundOrder($refundOrderDTO));
    }

    private function fetchOrder(int $eventId, int $orderId): OrderDomainObject
    {
        $order = $this->orderRepository
            ->loadRelation(new Relationship(StripePaymentDomainObject::class, name: 'stripe_payment'))
            ->findFirstWhere(['event_id' => $eventId, 'id' => $orderId]);

        if (!$order) {
            throw new ResourceNotFoundException();
        }

        return $order;
    }

    /**
     * @throws RefundNotPossibleException
     */
    private function validateRefundability(OrderDomainObject $order): void
    {
        if (!$order->getStripePayment()) {
            throw new RefundNotPossibleException(__('There is no Stripe data associated with this order.'));
        }

        if ($order->getRefundStatus() === OrderRefundStatus::REFUND_PENDING->name) {
            throw new RefundNotPossibleException(
                __('There is already a refund pending for this order.
                Please wait for the refund to be processed before requesting another one.')
            );
        }
    }

    private function notifyBuyer(OrderDomainObject $order, $event, MoneyValue $amount): void
    {
        $this->mailer->to($order->getEmail())->send(new OrderRefunded($order, $event, $amount));
    }

    private function markOrderRefundPending(OrderDomainObject $order): OrderDomainObject
    {
        return $this->orderRepository->updateFromArray(
            id: $order->getId(),
            attributes: [
                OrderDomainObjectAbstract::REFUND_STATUS => OrderRefundStatus::REFUND_PENDING->name,
            ]
        );
    }

    /**
     * @throws ApiErrorException
     * @throws UnknownCurrencyException
     * @throws RefundNotPossibleException
     * @throws Throwable
     * @throws RoundingNecessaryException
     * @throws MathException
     * @throws NumberFormatException
     */
    private function refundOrder(RefundOrderDTO $refundOrderDTO): OrderDomainObject
    {
        $order = $this->fetchOrder($refundOrderDTO->event_id, $refundOrderDTO->order_id);
        $event = $this->eventRepository->findById($refundOrderDTO->event_id);
        $amount = MoneyValue::fromFloat($refundOrderDTO->amount, $order->getCurrency());

        $this->validateRefundability($order);

        $this->refundService->refundPayment(
            amount: $amount,
            payment: $order->getStripePayment()
        );

        if ($refundOrderDTO->cancel_order) {
            $this->orderCancelService->cancelOrder($order);
        }

        if ($refundOrderDTO->notify_buyer) {
            $this->notifyBuyer($order, $event, $amount);
        }

        $this->eventStatisticsUpdateService->updateEventStatsTotalRefunded($order, $amount);

        return $this->markOrderRefundPending($order);
    }
}

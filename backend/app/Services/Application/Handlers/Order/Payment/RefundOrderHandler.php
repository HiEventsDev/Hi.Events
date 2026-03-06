<?php

namespace HiEvents\Services\Application\Handlers\Order\Payment;

use Brick\Math\Exception\MathException;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Exception\UnknownCurrencyException;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\RazorpayOrderDomainObject;
use HiEvents\DomainObjects\Status\OrderRefundStatus;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Exceptions\RefundNotPossibleException;
use HiEvents\Mail\Order\OrderRefunded;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Application\Handlers\Order\DTO\RefundOrderDTO;
use HiEvents\Services\Domain\Order\OrderCancelService;
use HiEvents\Services\Domain\Payment\Razorpay\RazorpayPaymentRefundService;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentIntentRefundService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use HiEvents\Values\MoneyValue;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\ConnectionInterface;
use Razorpay\Api\Errors\Error;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Throwable;

class RefundOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly Mailer $mailer,
        private readonly OrderCancelService $orderCancelService,
        private readonly ConnectionInterface $dbConnection,
        private readonly StripePaymentIntentRefundService $stripeRefundService,
        private readonly StripeClientFactory $stripeClientFactory,
        private readonly RazorpayPaymentRefundService $razorpayRefundService,
        private readonly Repository $config
    ) {
    }

    /**
     * @throws RefundNotPossibleException
     * @throws ApiErrorException
     * @throws Throwable
     */
    public function handle(RefundOrderDTO $refundOrderDTO): OrderDomainObject
    {
        return $this->dbConnection->transaction(fn() => $this->refundOrder($refundOrderDTO));
    }

    private function fetchOrder(int $eventId, int $orderId): OrderDomainObject
    {
        $order = $this->orderRepository
            ->loadRelation(new Relationship(StripePaymentDomainObject::class, name: 'stripe_payment'))
            ->loadRelation(new Relationship(RazorpayOrderDomainObject::class, name: 'razorpay_order'))
            ->findFirstWhere(['event_id' => $eventId, 'id' => $orderId]);

        if (!$order) {
            throw new ResourceNotFoundException(__('Order :id not found for event :eventId', [
                'id' => $orderId,
                'eventId' => $eventId,
            ]));
        }

        return $order;
    }

    /**
     * @throws RefundNotPossibleException
     */
    private function validateRefundability(OrderDomainObject $order): void
    {
        $payment = $order->getStripePayment() ?? $order->getRazorpayOrder();
        if (!$payment) {
            throw new RefundNotPossibleException(__('There is no payment data associated with this order.'));
        }

        if ($order->getRefundStatus() === OrderRefundStatus::REFUND_PENDING->name) {
            throw new RefundNotPossibleException(
                __('There is already a refund pending for this order.
                Please wait for the refund to be processed before requesting another one.')
            );
        }
    }

    private function notifyBuyer(OrderDomainObject $order, EventDomainObject $event, MoneyValue $amount): void
    {
        $this->mailer
            ->to($order->getEmail())
            ->locale($order->getLocale())
            ->send(new OrderRefunded(
                order: $order,
                event: $event,
                organizer: $event->getOrganizer(),
                eventSettings: $event->getEventSettings(),
                refundAmount: $amount
            ));
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

    private function generateRazorpayIdempotencyKey(OrderDomainObject $order): ?string
    {
        if (!$this->config->get('refunds.razorpay.idempotency_enabled', true)) {
            return null;
        }
        return 'refund_order_' . $order->getId() . '_' . time();
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
        $event = $this->eventRepository
            ->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
            ->loadRelation(EventSettingDomainObject::class)
            ->findById($refundOrderDTO->event_id);

        $amount = MoneyValue::fromFloat($refundOrderDTO->amount, $order->getCurrency());

        $this->validateRefundability($order);

        if ($refundOrderDTO->cancel_order) {
            $this->orderCancelService->cancelOrder($order);
        }

        if ($order->getStripePayment()) {
            // Determine the correct Stripe platform for this refund
            // Use the platform that was used for the original payment
            $paymentPlatform = $order->getStripePayment()->getStripePlatformEnum();

            // Create Stripe client for the original payment's platform
            $stripeClient = $this->stripeClientFactory->createForPlatform($paymentPlatform);

            $this->stripeRefundService->refundPayment(
                amount: $amount,
                payment: $order->getStripePayment(),
                stripeClient: $stripeClient
            );
        } elseif ($order->getRazorpayOrder()) {
            // Razorpay refund with idempotency and options
            $idempotencyKey = $this->generateRazorpayIdempotencyKey($order);
            $options = $refundOrderDTO->provider_options ?? [];

            try {
                $this->razorpayRefundService->refundPayment(
                    $order->getRazorpayOrder(),
                    $amount->toMinorUnit(),
                    $idempotencyKey,
                    $options
                );
            } catch (Error $e) {
                if ($e->getHttpStatusCode() === 409) {
                    throw new RefundNotPossibleException(
                        __('A refund for this order is already being processed. Please check again later.')
                    );
                }
                throw $e;
            }
        } else {
            throw new RefundNotPossibleException(__('No payment provider found for this order.'));
        }

        if ($refundOrderDTO->notify_buyer) {
            $this->notifyBuyer($order, $event, $amount);
        }

        return $this->markOrderRefundPending($order);
    }
}

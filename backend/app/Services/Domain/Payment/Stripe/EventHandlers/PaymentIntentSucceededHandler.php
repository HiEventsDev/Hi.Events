<?php

namespace HiEvents\Services\Domain\Payment\Stripe\EventHandlers;

use Brick\Math\Exception\MathException;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Exception\UnknownCurrencyException;
use Carbon\Carbon;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\StripePaymentDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Exceptions\CannotAcceptPaymentException;
use HiEvents\Repository\Eloquent\StripePaymentsRepository;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Payment\Stripe\StripeRefundExpiredOrderService;
use HiEvents\Services\Domain\Ticket\TicketQuantityUpdateService;
use Illuminate\Database\DatabaseManager;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Throwable;

readonly class PaymentIntentSucceededHandler
{
    public function __construct(
        private OrderRepositoryInterface        $orderRepository,
        private StripePaymentsRepository        $stripePaymentsRepository,
        private TicketQuantityUpdateService     $quantityUpdateService,
        private StripeRefundExpiredOrderService $refundExpiredOrderService,
        private DatabaseManager                 $databaseManager,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handleEvent(PaymentIntent $paymentIntent): void
    {
        $this->databaseManager->transaction(function () use ($paymentIntent) {
            /** @var StripePaymentDomainObjectAbstract $stripePayment */
            $stripePayment = $this->stripePaymentsRepository
                ->loadRelation(new Relationship(OrderDomainObject::class, name: 'order'))
                ->findFirstWhere([
                    StripePaymentDomainObjectAbstract::PAYMENT_INTENT_ID => $paymentIntent->id,
                ]);

            $this->validatePaymentAndOrderStatus($stripePayment, $paymentIntent);

            $this->updateStripePaymentInfo($paymentIntent, $stripePayment);

            $updatedOrder = $this->updateOrderStatuses($stripePayment);

            $this->quantityUpdateService->updateQuantitiesFromOrder($updatedOrder);

            OrderStatusChangedEvent::dispatch($updatedOrder);
        });
    }

    private function updateOrderStatuses(StripePaymentDomainObjectAbstract $stripePayment): OrderDomainObject
    {
        return $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->updateFromArray($stripePayment->getOrderId(), [
                OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::PAYMENT_RECEIVED->name,
                OrderDomainObjectAbstract::STATUS => OrderStatus::COMPLETED->name,
            ]);
    }

    private function updateStripePaymentInfo(PaymentIntent $paymentIntent, StripePaymentDomainObjectAbstract $stripePayment): void
    {
        $this->stripePaymentsRepository->updateWhere(
            attributes: [
                StripePaymentDomainObjectAbstract::LAST_ERROR => $paymentIntent->last_payment_error?->toArray(),
                StripePaymentDomainObjectAbstract::AMOUNT_RECEIVED => $paymentIntent->amount_received,
                StripePaymentDomainObjectAbstract::PAYMENT_METHOD_ID => is_string($paymentIntent->payment_method)
                    ? $paymentIntent->payment_method
                    : $paymentIntent->payment_method?->id,
                StripePaymentDomainObjectAbstract::CHARGE_ID => is_string($paymentIntent->latest_charge)
                    ? $paymentIntent->latest_charge
                    : $paymentIntent->latest_charge?->id,
            ],
            where: [
                StripePaymentDomainObjectAbstract::PAYMENT_INTENT_ID => $paymentIntent->id,
                StripePaymentDomainObjectAbstract::ORDER_ID => $stripePayment->getOrderId(),
            ]);
    }

    /**
     * If the order has expired (reserved_until is in the past), refund the payment and throw an exception.
     * This does seem quite extreme, but it ensures we don't oversell tickets. As far as I can see
     * this is how Ticketmaster and other ticketing systems work.
     *
     * @throws ApiErrorException
     * @throws RoundingNecessaryException
     * @throws CannotAcceptPaymentException
     * @throws MathException
     * @throws UnknownCurrencyException
     * @throws NumberFormatException
     * @todo We could check to see if there are tickets available, and if so, complete the order.
     *       This would be a better user experience.
     *
     */
    private function handleExpiredOrder(
        StripePaymentDomainObjectAbstract $stripePayment,
        PaymentIntent                     $paymentIntent,
    ): void
    {
        if ((new Carbon($stripePayment->getOrder()?->getReservedUntil()))->isPast()) {
            $this->refundExpiredOrderService->refundExpiredOrder(
                paymentIntent: $paymentIntent,
                stripePayment: $stripePayment,
                order: $stripePayment->getOrder(),
            );

            throw new CannotAcceptPaymentException(
                __('Payment was successful, but order has expired. Order: :id', [
                    'id' => $stripePayment->getOrderId()
                ])
            );
        }
    }

    /**
     * @throws ApiErrorException
     * @throws RoundingNecessaryException
     * @throws CannotAcceptPaymentException
     * @throws MathException
     * @throws UnknownCurrencyException
     * @throws NumberFormatException
     */
    private function validatePaymentAndOrderStatus(
        StripePaymentDomainObjectAbstract $stripePayment,
        PaymentIntent                     $paymentIntent
    ): void
    {
        if (!in_array($stripePayment->getOrder()?->getPaymentStatus(), [
            OrderPaymentStatus::AWAITING_PAYMENT->name,
            OrderPaymentStatus::PAYMENT_FAILED->name,
        ], true)) {
            throw new CannotAcceptPaymentException(
                __('Order is not awaiting payment. Order: :id',
                    ['id' => $stripePayment->getOrderId()]
                )
            );
        }

        $this->handleExpiredOrder($stripePayment, $paymentIntent);
    }
}

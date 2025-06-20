<?php

namespace HiEvents\Services\Domain\Payment\Stripe\EventHandlers;

use Brick\Math\Exception\MathException;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Exception\UnknownCurrencyException;
use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\StripePaymentDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderApplicationFeeStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Exceptions\CannotAcceptPaymentException;
use HiEvents\Repository\Eloquent\StripePaymentsRepository;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Order\OrderApplicationFeeService;
use HiEvents\Services\Domain\Payment\Stripe\StripeRefundExpiredOrderService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use Illuminate\Cache\Repository;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Throwable;

class PaymentIntentSucceededHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface        $orderRepository,
        private readonly StripePaymentsRepository        $stripePaymentsRepository,
        private readonly AffiliateRepositoryInterface    $affiliateRepository,
        private readonly ProductQuantityUpdateService    $quantityUpdateService,
        private readonly StripeRefundExpiredOrderService $refundExpiredOrderService,
        private readonly AttendeeRepositoryInterface     $attendeeRepository,
        private readonly DatabaseManager                 $databaseManager,
        private readonly LoggerInterface                 $logger,
        private readonly Repository                      $cache,
        private readonly DomainEventDispatcherService    $domainEventDispatcherService,
        private readonly OrderApplicationFeeService      $orderApplicationFeeService,
    )
    {
    }

    /**
     * @throws Throwable
     */
    public function handleEvent(PaymentIntent $paymentIntent): void
    {
        if ($this->isPaymentIntentAlreadyHandled($paymentIntent)) {
            $this->logger->info('Payment intent already handled', [
                'payment_intent' => $paymentIntent->id,
            ]);

            return;
        }

        $this->databaseManager->transaction(function () use ($paymentIntent) {
            /** @var StripePaymentDomainObjectAbstract $stripePayment */
            $stripePayment = $this->stripePaymentsRepository
                ->loadRelation(new Relationship(OrderDomainObject::class, name: 'order'))
                ->findFirstWhere([
                    StripePaymentDomainObjectAbstract::PAYMENT_INTENT_ID => $paymentIntent->id,
                ]);

            if (!$stripePayment) {
                $this->logger->error('Payment intent not found when handling payment intent succeeded event', [
                    'paymentIntent' => $paymentIntent->toArray(),
                ]);

                return;
            }

            $this->validatePaymentAndOrderStatus($stripePayment, $paymentIntent);

            $this->updateStripePaymentInfo($paymentIntent, $stripePayment);

            $updatedOrder = $this->updateOrderStatuses($stripePayment);

            $this->updateAttendeeStatuses($updatedOrder);

            $this->quantityUpdateService->updateQuantitiesFromOrder($updatedOrder);

            OrderStatusChangedEvent::dispatch($updatedOrder);

            $this->domainEventDispatcherService->dispatch(
                new OrderEvent(
                    type: DomainEventType::ORDER_CREATED,
                    orderId: $updatedOrder->getId()
                ),
            );

            $this->markPaymentIntentAsHandled($paymentIntent, $updatedOrder);

            $this->storeApplicationFeePayment($updatedOrder, $paymentIntent);
        });
    }

    private function updateOrderStatuses(StripePaymentDomainObjectAbstract $stripePayment): OrderDomainObject
    {
        $updatedOrder = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->updateFromArray($stripePayment->getOrderId(), [
                OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::PAYMENT_RECEIVED->name,
                OrderDomainObjectAbstract::STATUS => OrderStatus::COMPLETED->name,
                OrderDomainObjectAbstract::PAYMENT_PROVIDER => PaymentProviders::STRIPE->value,
            ]);

        // Update affiliate sales if this order has an affiliate
        if ($updatedOrder->getAffiliateId()) {
            $this->affiliateRepository->incrementSales(
                affiliateId: $updatedOrder->getAffiliateId(),
                amount: $updatedOrder->getTotalGross()
            );
        }

        return $updatedOrder;
    }

    private function updateStripePaymentInfo(PaymentIntent $paymentIntent, StripePaymentDomainObjectAbstract $stripePayment): void
    {
        $this->stripePaymentsRepository->updateWhere(
            attributes: [
                StripePaymentDomainObjectAbstract::LAST_ERROR => $paymentIntent->last_payment_error?->toArray(),
                StripePaymentDomainObjectAbstract::AMOUNT_RECEIVED => $paymentIntent->amount_received,
                StripePaymentDomainObjectAbstract::APPLICATION_FEE => $paymentIntent->application_fee_amount ?? 0,
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
     * This does seem quite extreme, but it ensures we don't oversell products. As far as I can see
     * this is how Ticketmaster and other ticketing systems work.
     *
     * @throws ApiErrorException
     * @throws RoundingNecessaryException
     * @throws CannotAcceptPaymentException
     * @throws MathException
     * @throws UnknownCurrencyException
     * @throws NumberFormatException
     * @todo We could check to see if there are products available, and if so, complete the order.
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

    private function updateAttendeeStatuses(OrderDomainObject $updatedOrder): void
    {
        $this->attendeeRepository->updateWhere(
            attributes: [
                'status' => AttendeeStatus::ACTIVE->name,
            ],
            where: [
                'order_id' => $updatedOrder->getId(),
                'status' => AttendeeStatus::AWAITING_PAYMENT->name,
            ],
        );
    }

    private function markPaymentIntentAsHandled(PaymentIntent $paymentIntent, OrderDomainObject $updatedOrder): void
    {
        $this->logger->info('Stripe payment intent succeeded event handled', [
            'payment_intent' => $paymentIntent->id,
            'order_id' => $updatedOrder->getId(),
            'amount_received' => $paymentIntent->amount_received,
            'currency' => $paymentIntent->currency,
        ]);

        $this->cache->put('payment_intent_handled_' . $paymentIntent->id, true, 3600);
    }

    private function isPaymentIntentAlreadyHandled(PaymentIntent $paymentIntent): bool
    {
        return $this->cache->has('payment_intent_handled_' . $paymentIntent->id);
    }

    private function storeApplicationFeePayment(OrderDomainObject $updatedOrder, PaymentIntent $paymentIntent): void
    {
        $this->orderApplicationFeeService->createOrderApplicationFee(
            orderId: $updatedOrder->getId(),
            applicationFeeAmountMinorUnit: $paymentIntent->application_fee_amount ?? 0,
            orderApplicationFeeStatus: OrderApplicationFeeStatus::PAID,
            paymentMethod: PaymentProviders::STRIPE,
            currency: $updatedOrder->getCurrency(),
        );
    }
}

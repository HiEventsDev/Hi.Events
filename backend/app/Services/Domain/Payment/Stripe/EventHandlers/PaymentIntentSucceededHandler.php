<?php

namespace HiEvents\Services\Domain\Payment\Stripe\EventHandlers;

use Brick\Math\Exception\MathException;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Exception\UnknownCurrencyException;
use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\EventSettingDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\StripePaymentDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\AttendeeStatus;
use HiEvents\DomainObjects\Status\OrderApplicationFeeStatus;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Enterprise\Seating\Services\Domain\SeatedOrderCompletionGuard;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Exceptions\CannotAcceptPaymentException;
use HiEvents\Exceptions\OrderNotCompletableException;
use HiEvents\Exceptions\PaymentRefundedException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Repository\Eloquent\StripePaymentsRepository;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Order\OccurrenceStatusValidator;
use HiEvents\Services\Domain\Order\OrderApplicationFeeService;
use HiEvents\Services\Domain\Payment\Stripe\StripeRefundExpiredOrderService;
use HiEvents\Services\Domain\Product\ProductQuantityUpdateService;
use HiEvents\Services\Infrastructure\DomainEvents\DomainEventDispatcherService;
use HiEvents\Services\Infrastructure\DomainEvents\Enums\DomainEventType;
use HiEvents\Services\Infrastructure\DomainEvents\Events\OrderEvent;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use Illuminate\Cache\Repository;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Throwable;

class PaymentIntentSucceededHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly StripePaymentsRepository $stripePaymentsRepository,
        private readonly AffiliateRepositoryInterface $affiliateRepository,
        private readonly ProductQuantityUpdateService $quantityUpdateService,
        private readonly StripeRefundExpiredOrderService $refundExpiredOrderService,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly DatabaseManager $databaseManager,
        private readonly LoggerInterface $logger,
        private readonly Repository $cache,
        private readonly DomainEventDispatcherService $domainEventDispatcherService,
        private readonly OrderApplicationFeeService $orderApplicationFeeService,
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,
        private readonly OccurrenceStatusValidator $occurrenceStatusValidator,
        private readonly SeatedOrderCompletionGuard $seatedOrderCompletionGuard,
        private readonly TransactionLockService $transactionLockService,
    ) {}

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

        try {
            $result = $this->completeOrder($paymentIntent);
        } catch (OrderNotCompletableException $exception) {
            $this->refundNotCompletableOrder($paymentIntent, $exception);

            return;
        }

        if ($result === null) {
            return;
        }

        $orderHasEmail = $result['order']->getEmail() !== null;

        event(new OrderStatusChangedEvent(
            $result['order'],
            sendEmails: $orderHasEmail,
            createInvoice: $result['eventSettings']->getEnableInvoicing() && $orderHasEmail,
        ));

        $this->domainEventDispatcherService->dispatch(
            new OrderEvent(
                type: DomainEventType::ORDER_CREATED,
                orderId: $result['order']->getId()
            ),
        );
    }

    /**
     * @throws Throwable
     */
    private function completeOrder(PaymentIntent $paymentIntent): ?array
    {
        return $this->databaseManager->transaction(function () use ($paymentIntent) {
            $stripePayment = $this->findStripePayment($paymentIntent);

            if (! $stripePayment) {
                $this->logger->error('Payment intent not found when handling payment intent succeeded event', [
                    'paymentIntent' => $paymentIntent->toArray(),
                ]);

                return null;
            }

            if ($stripePayment->getOrder() === null) {
                throw new CannotAcceptPaymentException(
                    __('Payment was successful, but the order is no longer valid. Order: :id', [
                        'id' => $stripePayment->getOrderId(),
                    ])
                );
            }

            $this->transactionLockService->lockOrder($stripePayment->getOrder()->getShortId());

            if ($this->isPaymentIntentAlreadyHandled($paymentIntent)) {
                return null;
            }

            $stripePayment = $this->findStripePayment($paymentIntent);

            if ($this->isAlreadyPaidByStripe($stripePayment)) {
                if ($this->isAnotherPaymentForAPaidOrder($stripePayment)) {
                    throw new OrderNotCompletableException(
                        __('Payment was successful, but the order was already paid by another payment. Order: :id', [
                            'id' => $stripePayment->getOrderId(),
                        ]),
                        $stripePayment,
                        notifyBuyer: false,
                    );
                }

                $this->markPaymentIntentAsHandled($paymentIntent, $stripePayment->getOrder());

                return null;
            }

            $this->validatePaymentAndOrderStatus($stripePayment);

            $this->updateStripePaymentInfo($paymentIntent, $stripePayment);

            $updatedOrder = $this->updateOrderStatuses($stripePayment);

            $this->updateAttendeeStatuses($updatedOrder);

            $this->quantityUpdateService->updateQuantitiesFromOrder($updatedOrder);

            /** @var EventSettingDomainObject $eventSettings */
            $eventSettings = $this->eventSettingsRepository->findFirstWhere([
                EventSettingDomainObjectAbstract::EVENT_ID => $updatedOrder->getEventId(),
            ]);

            $this->markPaymentIntentAsHandled($paymentIntent, $updatedOrder);

            $this->storeApplicationFeePayment($updatedOrder, $paymentIntent);

            return ['order' => $updatedOrder, 'eventSettings' => $eventSettings];
        });
    }

    private function findStripePayment(PaymentIntent $paymentIntent): ?StripePaymentDomainObjectAbstract
    {
        return $this->stripePaymentsRepository
            ->loadRelation(new Relationship(OrderDomainObject::class, name: 'order', nested: [
                new Relationship(OrderItemDomainObject::class),
            ]))
            ->findFirstWhere([
                StripePaymentDomainObjectAbstract::PAYMENT_INTENT_ID => $paymentIntent->id,
            ]);
    }

    private function isAlreadyPaidByStripe(StripePaymentDomainObjectAbstract $stripePayment): bool
    {
        $order = $stripePayment->getOrder();

        return $order->getPaymentStatus() === OrderPaymentStatus::PAYMENT_RECEIVED->name
            && $order->getPaymentProvider() === PaymentProviders::STRIPE->value;
    }

    private function isAnotherPaymentForAPaidOrder(StripePaymentDomainObjectAbstract $stripePayment): bool
    {
        if ($stripePayment->getChargeId() !== null) {
            return false;
        }

        return $this->stripePaymentsRepository
            ->findWhere([StripePaymentDomainObjectAbstract::ORDER_ID => $stripePayment->getOrderId()])
            ->contains(fn (StripePaymentDomainObjectAbstract $payment) => $payment->getPaymentIntentId() !== $stripePayment->getPaymentIntentId()
                && $payment->getChargeId() !== null);
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
     * @throws OrderNotCompletableException
     */
    private function handleExpiredOrder(StripePaymentDomainObjectAbstract $stripePayment): void
    {
        $order = $stripePayment->getOrder();
        $reservedUntil = new Carbon($order->getReservedUntil());

        if ($reservedUntil->isPast() && ! $this->seatedOrderCompletionGuard->canRescueExpired($order)) {
            $this->rejectForRefund(
                $stripePayment,
                __('Payment was successful, but order has expired. Order: :id', [
                    'id' => $stripePayment->getOrderId(),
                ])
            );
        }

        if ($reservedUntil->isPast()) {
            return;
        }

        try {
            $this->seatedOrderCompletionGuard->assertSeatsHeld($order);
        } catch (ResourceConflictException) {
            $this->rejectForRefund(
                $stripePayment,
                __('Payment was successful, but the seats are no longer held. Order: :id', [
                    'id' => $stripePayment->getOrderId(),
                ])
            );
        }
    }

    /**
     * @throws OrderNotCompletableException
     */
    private function rejectForRefund(
        StripePaymentDomainObjectAbstract $stripePayment,
        string $message,
        bool $notifyBuyer = true,
    ): never {
        throw new OrderNotCompletableException($message, $stripePayment, $notifyBuyer);
    }

    /**
     * @throws PaymentRefundedException
     * @throws ApiErrorException
     * @throws RoundingNecessaryException
     * @throws MathException
     * @throws UnknownCurrencyException
     * @throws NumberFormatException
     * @throws StripeClientConfigurationException
     * @throws Throwable
     */
    private function refundNotCompletableOrder(PaymentIntent $paymentIntent, OrderNotCompletableException $exception): void
    {
        $stripePayment = $exception->stripePayment;

        $refund = $this->databaseManager->transaction(function () use ($paymentIntent, $stripePayment, $exception) {
            $this->transactionLockService->lockOrder($stripePayment->getOrder()->getShortId());

            if ($this->isPaymentIntentAlreadyHandled($paymentIntent)
                || $this->refundExpiredOrderService->hasRefunded($stripePayment->getOrder()->getId(), $paymentIntent->id)) {
                return null;
            }

            $refund = $this->refundExpiredOrderService->refundExpiredOrder(
                paymentIntent: $paymentIntent,
                stripePayment: $stripePayment,
                order: $stripePayment->getOrder(),
                notifyBuyer: $exception->notifyBuyer,
            );

            $this->cache->put('payment_intent_handled_'.$paymentIntent->id, true, 3600);

            return $refund;
        });

        if ($refund === null) {
            return;
        }

        $this->refundExpiredOrderService->recordRefund($refund);

        throw new PaymentRefundedException($exception->getMessage(), $refund);
    }

    /**
     * @throws CannotAcceptPaymentException
     * @throws OrderNotCompletableException
     */
    private function validatePaymentAndOrderStatus(StripePaymentDomainObjectAbstract $stripePayment): void
    {
        if ($stripePayment->getOrder()->isBoxOfficeOrder()
            && ! $stripePayment->getOrder()->isOrderReserved()) {
            $this->rejectForRefund(
                $stripePayment,
                __('Payment was successful, but the sale was already completed with another tender. Order: :id', [
                    'id' => $stripePayment->getOrderId(),
                ]),
                notifyBuyer: false,
            );
        }

        if (! in_array($stripePayment->getOrder()->getPaymentStatus(), [
            OrderPaymentStatus::AWAITING_PAYMENT->name,
            OrderPaymentStatus::PAYMENT_FAILED->name,
        ], true)) {
            throw new CannotAcceptPaymentException(
                __('Order is not awaiting payment. Order: :id',
                    ['id' => $stripePayment->getOrderId()]
                )
            );
        }

        if (in_array($stripePayment->getOrder()->getStatus(), [
            OrderStatus::CANCELLED->name,
            OrderStatus::ABANDONED->name,
        ], true)) {
            $this->rejectForRefund(
                $stripePayment,
                __('Payment was successful, but the order is no longer valid. Order: :id', [
                    'id' => $stripePayment->getOrderId(),
                ])
            );
        }

        $order = $stripePayment->getOrder();

        if ($this->occurrenceStatusValidator->findBlockingOccurrence($order, allowPastOccurrence: $order->isBoxOfficeOrder()) !== null) {
            $this->rejectForRefund(
                $stripePayment,
                __('Payment was successful, but the event date is no longer available. Order: :id', [
                    'id' => $stripePayment->getOrderId(),
                ])
            );
        }

        $this->handleExpiredOrder($stripePayment);
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

        $this->cache->put('payment_intent_handled_'.$paymentIntent->id, true, 3600);
    }

    private function isPaymentIntentAlreadyHandled(PaymentIntent $paymentIntent): bool
    {
        return $this->cache->has('payment_intent_handled_'.$paymentIntent->id);
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

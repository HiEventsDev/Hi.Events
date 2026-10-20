<?php

namespace HiEvents\Services\Domain\Payment\Stripe;

use Brick\Math\Exception\MathException;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Exception\UnknownCurrencyException;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderRefundDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Mail\Order\PaymentSuccessButOrderExpiredMail;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRefundRepositoryInterface;
use HiEvents\Services\Domain\Payment\Stripe\DTOs\NotCompletableOrderRefundDTO;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use HiEvents\Values\MoneyValue;
use Illuminate\Contracts\Mail\Mailer;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;

readonly class StripeRefundExpiredOrderService
{
    private const REASON_ORDER_NOT_COMPLETABLE = 'order_not_completable';

    public function __construct(
        private StripePaymentIntentRefundService $refundService,
        private Mailer $mailer,
        private LoggerInterface $logger,
        private EventRepositoryInterface $eventRepository,
        private StripeClientFactory $stripeClientFactory,
        private OrderRefundRepositoryInterface $orderRefundRepository,
    ) {}

    /**
     * @throws ApiErrorException
     * @throws RoundingNecessaryException
     * @throws MathException
     * @throws UnknownCurrencyException
     * @throws NumberFormatException
     * @throws StripeClientConfigurationException
     */
    public function refundExpiredOrder(
        PaymentIntent $paymentIntent,
        StripePaymentDomainObject $stripePayment,
        OrderDomainObject $order,
        bool $notifyBuyer = true,
    ): NotCompletableOrderRefundDTO {
        $event = $this->eventRepository
            ->loadRelation(new Relationship(EventSettingDomainObject::class))
            ->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
            ->findById($order->getEventId());

        $stripeClient = $this->stripeClientFactory->createForPlatform($stripePayment->getStripePlatformEnum());
        $amount = MoneyValue::fromMinorUnit($paymentIntent->amount, strtoupper($paymentIntent->currency));

        $refund = $this->refundService->refundPayment($amount, $stripePayment, $stripeClient);

        $this->logger->info('Refunded payment for an order that could not be completed', [
            'order_id' => $order->getId(),
            'event_id' => $event->getId(),
            'payment_intent_id' => $paymentIntent->id,
            'refund_id' => $refund->id,
        ]);

        $refundRecord = new NotCompletableOrderRefundDTO(
            orderId: $order->getId(),
            refundId: $refund->id,
            amount: $amount->toFloat(),
            currency: $order->getCurrency(),
            status: $refund->status,
            paymentIntentId: $paymentIntent->id,
        );

        if (! $notifyBuyer || $order->getEmail() === null) {
            return $refundRecord;
        }

        $this->mailer
            ->to($order->getEmail())
            ->locale($order->getLocale())
            ->send((new PaymentSuccessButOrderExpiredMail(
                order: $order,
                event: $event,
                eventSettings: $event->getEventSettings(),
                organizer: $event->getOrganizer(),
            ))->beforeCommit());

        return $refundRecord;
    }

    public function hasRefunded(int $orderId, string $paymentIntentId): bool
    {
        return $this->orderRefundRepository
            ->findWhere(['order_id' => $orderId])
            ->contains(fn (OrderRefundDomainObject $refund) => is_array($refund->getMetadata())
                && ($refund->getMetadata()['reason'] ?? null) === self::REASON_ORDER_NOT_COMPLETABLE
                && ($refund->getMetadata()['payment_intent'] ?? null) === $paymentIntentId);
    }

    public function recordRefund(NotCompletableOrderRefundDTO $refund): void
    {
        if ($this->orderRefundRepository->findFirstWhere(['refund_id' => $refund->refundId]) !== null) {
            return;
        }

        $this->orderRefundRepository->create([
            'order_id' => $refund->orderId,
            'payment_provider' => PaymentProviders::STRIPE->value,
            'refund_id' => $refund->refundId,
            'amount' => $refund->amount,
            'currency' => $refund->currency,
            'status' => $refund->status,
            'metadata' => ['payment_intent' => $refund->paymentIntentId, 'reason' => self::REASON_ORDER_NOT_COMPLETABLE],
        ]);
    }
}

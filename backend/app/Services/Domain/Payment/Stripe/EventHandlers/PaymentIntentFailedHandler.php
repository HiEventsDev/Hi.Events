<?php

namespace HiEvents\Services\Domain\Payment\Stripe\EventHandlers;

use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\StripePaymentDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal\TerminalAttemptTracker;
use HiEvents\Events\OrderStatusChangedEvent;
use HiEvents\Repository\Eloquent\StripePaymentsRepository;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentUpdateFromPaymentIntentService;
use Illuminate\Database\DatabaseManager;
use Stripe\PaymentIntent;
use Throwable;

readonly class PaymentIntentFailedHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private StripePaymentsRepository $stripePaymentsRepository,
        private DatabaseManager $databaseManager,
        private StripePaymentUpdateFromPaymentIntentService $stripePaymentUpdateFromPaymentIntentService,
        private TerminalAttemptTracker $attemptTracker,
    ) {}

    /**
     * @throws Throwable
     */
    public function handleEvent(PaymentIntent $paymentIntent, ?int $eventCreatedAt = null): void
    {
        if ($this->attemptTracker->predatesCurrentAttempt($paymentIntent->id, $eventCreatedAt)) {
            return;
        }

        $this->databaseManager->transaction(function () use ($paymentIntent) {
            /** @var StripePaymentDomainObjectAbstract $stripePayment */
            $stripePayment = $this->stripePaymentsRepository
                ->loadRelation(new Relationship(OrderDomainObject::class, name: 'order'))
                ->findFirstWhere([
                    StripePaymentDomainObjectAbstract::PAYMENT_INTENT_ID => $paymentIntent->id,
                ]);

            if (! $stripePayment) {
                return;
            }

            $this->stripePaymentUpdateFromPaymentIntentService->updateStripePaymentInfo($paymentIntent, $stripePayment);

            $updatedOrder = $this->updateOrderStatuses($stripePayment);

            if ($updatedOrder !== null) {
                OrderStatusChangedEvent::dispatch($updatedOrder);
            }
        });
    }

    private function updateOrderStatuses(StripePaymentDomainObjectAbstract $stripePayment): ?OrderDomainObject
    {
        $affected = $this->orderRepository->updateWhere(
            attributes: [
                OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::PAYMENT_FAILED->name,
            ],
            where: [
                OrderDomainObjectAbstract::ID => $stripePayment->getOrderId(),
                OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::AWAITING_PAYMENT->name,
            ],
        );

        if ($affected === 0) {
            return null;
        }

        return $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->findById($stripePayment->getOrderId());
    }
}

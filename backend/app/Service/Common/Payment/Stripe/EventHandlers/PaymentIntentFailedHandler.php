<?php

namespace TicketKitten\Service\Common\Payment\Stripe\EventHandlers;

use Illuminate\Database\DatabaseManager;
use Stripe\PaymentIntent;
use Throwable;
use TicketKitten\DomainObjects\Generated\OrderDomainObjectAbstract;
use TicketKitten\DomainObjects\Generated\StripePaymentDomainObjectAbstract;
use TicketKitten\DomainObjects\OrderDomainObject;
use TicketKitten\DomainObjects\OrderItemDomainObject;
use TicketKitten\DomainObjects\Status\OrderPaymentStatus;
use TicketKitten\Events\OrderStatusChangedEvent;
use TicketKitten\Repository\Eloquent\StripePaymentsRepository;
use TicketKitten\Repository\Eloquent\Value\Relationship;
use TicketKitten\Repository\Interfaces\OrderRepositoryInterface;
use TicketKitten\Service\Common\Payment\Stripe\StripePaymentUpdateFromPaymentIntentService;

readonly class PaymentIntentFailedHandler
{
    public function __construct(
        private OrderRepositoryInterface                    $orderRepository,
        private StripePaymentsRepository                    $stripePaymentsRepository,
        private DatabaseManager                             $databaseManager,
        private StripePaymentUpdateFromPaymentIntentService $stripePaymentUpdateFromPaymentIntentService,
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

            $this->stripePaymentUpdateFromPaymentIntentService->updateStripePaymentInfo($paymentIntent, $stripePayment);

            $updatedOrder = $this->updateOrderStatuses($stripePayment);

            OrderStatusChangedEvent::dispatch($updatedOrder);
        });
    }

    private function updateOrderStatuses(StripePaymentDomainObjectAbstract $stripePayment): OrderDomainObject
    {
        return $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->updateFromArray($stripePayment->getOrderId(), [
                OrderDomainObjectAbstract::PAYMENT_STATUS => OrderPaymentStatus::PAYMENT_FAILED->name,
            ]);
    }
}

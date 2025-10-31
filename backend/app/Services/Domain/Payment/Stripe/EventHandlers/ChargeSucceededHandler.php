<?php

namespace HiEvents\Services\Domain\Payment\Stripe\EventHandlers;

use HiEvents\DomainObjects\Generated\StripePaymentDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Repository\Eloquent\StripePaymentsRepository;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentPlatformFeeExtractionService;
use Psr\Log\LoggerInterface;
use Stripe\Charge;

class ChargeSucceededHandler
{
    public function __construct(
        private readonly StripePaymentsRepository                  $stripePaymentsRepository,
        private readonly StripePaymentPlatformFeeExtractionService $platformFeeExtractionService,
        private readonly LoggerInterface                           $logger,
    )
    {
    }

    public function handleEvent(Charge $charge): void
    {
        $this->logger->info(__('Processing charge event'), [
            'charge_id' => $charge->id,
            'payment_intent_id' => $charge->payment_intent,
            'status' => $charge->status,
        ]);

        if ($charge->status !== 'succeeded') {
            $this->logger->info(__('Charge not in succeeded status, skipping'), [
                'charge_id' => $charge->id,
                'status' => $charge->status,
            ]);
            return;
        }

        /**@var StripePaymentDomainObject $stripePayment */
        $stripePayment = $this->stripePaymentsRepository
            ->loadRelation(new Relationship(OrderDomainObject::class, name: 'order'))
            ->findFirstWhere([
                StripePaymentDomainObjectAbstract::PAYMENT_INTENT_ID => $charge->payment_intent,
            ]);

        if (!$stripePayment) {
            $this->logger->warning(__('Stripe payment not found for charge'), [
                'charge_id' => $charge->id,
                'payment_intent_id' => $charge->payment_intent,
            ]);
            return;
        }

        $order = $stripePayment->getOrder();
        if (!$order) {
            $this->logger->warning(__('Order not found for charge'), [
                'charge_id' => $charge->id,
                'payment_intent_id' => $charge->payment_intent,
                'stripe_payment_id' => $stripePayment->getId(),
            ]);
            return;
        }

        $this->platformFeeExtractionService->extractAndStorePlatformFee(
            order: $order,
            charge: $charge,
            stripePayment: $stripePayment
        );
    }
}

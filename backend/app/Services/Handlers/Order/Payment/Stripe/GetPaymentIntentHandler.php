<?php

namespace HiEvents\Services\Handlers\Order\Payment\Stripe;

use HiEvents\DomainObjects\Status\OrderPaymentStatus;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\PaymentIntentSucceededHandler;
use HiEvents\Services\Handlers\Order\Payment\Stripe\DTO\StripePaymentIntentPublicDTO;
use Psr\Log\LoggerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

readonly class GetPaymentIntentHandler
{
    public function __construct(
        private StripeClient                  $stripeClient,
        private OrderRepositoryInterface      $orderRepository,
        private LoggerInterface               $logger,
        private PaymentIntentSucceededHandler $paymentIntentSucceededHandler,
    )
    {
    }

    public function handle(int $eventId, string $orderShortId): StripePaymentIntentPublicDTO
    {
        $order = $this->orderRepository
            ->loadRelation(new Relationship(
                domainObject: StripePaymentDomainObject::class,
                name: 'stripe_payment',
            ))
            ->findFirstWhere([
                'event_id' => $eventId,
                'short_id' => $orderShortId
            ]);

        $accountId = $order->getStripePayment()->getConnectedAccountId();

        try {
            $paymentIntent = $this->stripeClient->paymentIntents->retrieve(
                id: $order->getStripePayment()->getPaymentIntentId(),
                opts: $accountId ? ['stripe_account' => $accountId] : []
            );
        } catch (ApiErrorException $e) {
            $this->logger->error('Failed to retrieve payment intent', [
                'error' => $e->getMessage(),
                'order_id' => $order->getId(),
                'order_short_id' => $order->getShortId(),
                'payment_intent_id' => $order->getStripePayment()->getPaymentIntentId(),
            ]);

            throw new ResourceNotFoundException('Payment intent not found: ' . $e->getMessage());
        }

        // If the payment intent is a success and the order's payment status is not received, we manually handle the event here.
        // This is because the webhook may not have been received yet, or has failed for some reason.
        // This is a safety net to ensure the order is updated correctly.
        if ($paymentIntent->status === 'succeeded' && $order->getPaymentStatus() !== OrderPaymentStatus::PAYMENT_RECEIVED->name) {
            $this->paymentIntentSucceededHandler->handleEvent($paymentIntent);
        }

        return StripePaymentIntentPublicDTO::fromArray([
            'paymentIntentId' => $paymentIntent->id,
            'status' => $paymentIntent->status,
            'amount' => $paymentIntent->amount,
        ]);
    }
}

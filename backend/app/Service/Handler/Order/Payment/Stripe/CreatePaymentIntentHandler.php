<?php

namespace TicketKitten\Service\Handler\Order\Payment\Stripe;

use Brick\Math\Exception\MathException;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use TicketKitten\DomainObjects\Generated\StripePaymentDomainObjectAbstract;
use TicketKitten\DomainObjects\OrderItemDomainObject;
use TicketKitten\DomainObjects\StripePaymentDomainObject;
use TicketKitten\Exceptions\Stripe\CreatePaymentIntentFailedException;
use TicketKitten\Exceptions\UnauthorizedException;
use TicketKitten\Repository\Eloquent\Value\Relationship;
use TicketKitten\Repository\Interfaces\AccountRepositoryInterface;
use TicketKitten\Repository\Interfaces\OrderRepositoryInterface;
use TicketKitten\Repository\Interfaces\StripePaymentsRepositoryInterface;
use TicketKitten\Service\Common\Payment\Stripe\DTOs\CreatePaymentIntentRequestDTO;
use TicketKitten\Service\Common\Payment\Stripe\DTOs\CreatePaymentIntentResponseDTO;
use TicketKitten\Service\Common\Payment\Stripe\StripePaymentIntentCreationService;
use TicketKitten\Service\Common\Session\SessionIdentifierService;

readonly class CreatePaymentIntentHandler
{
    public function __construct(
        private OrderRepositoryInterface           $orderRepository,
        private StripePaymentIntentCreationService $stripePaymentService,
        private SessionIdentifierService           $sessionIdentifierService,
        private StripePaymentsRepositoryInterface  $stripePaymentsRepository,
        private AccountRepositoryInterface         $accountRepository
    )
    {
    }

    /**
     * @throws MathException
     * @throws NumberFormatException
     * @throws RoundingNecessaryException
     * @throws UnknownCurrencyException
     * @throws CreatePaymentIntentFailedException
     */
    public function handle(string $orderShortId): CreatePaymentIntentResponseDTO
    {
        $order = $this->orderRepository
            ->loadRelation(new Relationship(OrderItemDomainObject::class))
            ->loadRelation(new Relationship(StripePaymentDomainObject::class, name: 'stripe_payment'))
            ->findByShortId($orderShortId);


        if (!$order || !$this->sessionIdentifierService->verifyIdentifier($order->getSessionId())) {
            throw new UnauthorizedException();
        }

        $account = $this->accountRepository->findByEventId($order->getEventId());

        // If we already have a Stripe session then re-fetch the client secret
        if ($order->getStripePayment() !== null) {
            return new CreatePaymentIntentResponseDTO(
                paymentIntentId: $order->getStripePayment()->getPaymentIntentId(),
                clientSecret: $this->stripePaymentService->retrievePaymentIntentClientSecret(
                    $order->getStripePayment()->getPaymentIntentId(),
                    $account->getStripeAccountId()
                ),
                accountId: $account->getStripeAccountId(),
            );
        }

        $paymentIntent = $this->stripePaymentService->createPaymentIntent(CreatePaymentIntentRequestDTO::fromArray([
            'amount' => Money::of($order->getTotalGross(), $order->getCurrency())->getMinorAmount()->toInt(),
            'currencyCode' => $order->getCurrency(),
            'account' => $account,
        ]));

        $this->stripePaymentsRepository->create([
            StripePaymentDomainObjectAbstract::ORDER_ID => $order->getId(),
            StripePaymentDomainObjectAbstract::PAYMENT_INTENT_ID => $paymentIntent->paymentIntentId,
            StripePaymentDomainObjectAbstract::CONNECTED_ACCOUNT_ID => $account->getStripeAccountId(),
        ]);

        return $paymentIntent;
    }
}

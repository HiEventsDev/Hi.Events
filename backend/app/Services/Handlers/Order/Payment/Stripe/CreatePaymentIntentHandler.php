<?php

namespace HiEvents\Services\Handlers\Order\Payment\Stripe;

use Brick\Math\Exception\MathException;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Exception\UnknownCurrencyException;
use Brick\Money\Money;
use HiEvents\DomainObjects\Generated\StripePaymentDomainObjectAbstract;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Exceptions\Stripe\CreatePaymentIntentFailedException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\StripePaymentsRepositoryInterface;
use HiEvents\Services\Domain\Payment\Stripe\DTOs\CreatePaymentIntentRequestDTO;
use HiEvents\Services\Domain\Payment\Stripe\DTOs\CreatePaymentIntentResponseDTO;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentIntentCreationService;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;

readonly class CreatePaymentIntentHandler
{
    public function __construct(
        private OrderRepositoryInterface           $orderRepository,
        private StripePaymentIntentCreationService $stripePaymentService,
        private CheckoutSessionManagementService   $sessionIdentifierService,
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

        if (!$order || !$this->sessionIdentifierService->verifySession($order->getSessionId())) {
            throw new UnauthorizedException(__('Sorry, we could not verify your session. Please create a new order.'));
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
            'order' => $order,
        ]));

        $this->stripePaymentsRepository->create([
            StripePaymentDomainObjectAbstract::ORDER_ID => $order->getId(),
            StripePaymentDomainObjectAbstract::PAYMENT_INTENT_ID => $paymentIntent->paymentIntentId,
            StripePaymentDomainObjectAbstract::CONNECTED_ACCOUNT_ID => $account->getStripeAccountId(),
        ]);

        return $paymentIntent;
    }
}

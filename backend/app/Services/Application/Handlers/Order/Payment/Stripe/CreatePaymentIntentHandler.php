<?php

namespace HiEvents\Services\Application\Handlers\Order\Payment\Stripe;

use Brick\Math\Exception\MathException;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Exception\UnknownCurrencyException;
use HiEvents\DomainObjects\AccountConfigurationDomainObject;
use HiEvents\DomainObjects\AccountStripePlatformDomainObject;
use HiEvents\DomainObjects\Generated\StripePaymentDomainObjectAbstract;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\Stripe\CreatePaymentIntentFailedException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\StripePaymentsRepositoryInterface;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use HiEvents\Services\Infrastructure\Stripe\StripeConfigurationService;
use HiEvents\Services\Domain\Payment\Stripe\DTOs\CreatePaymentIntentRequestDTO;
use HiEvents\Services\Domain\Payment\Stripe\DTOs\CreatePaymentIntentResponseDTO;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentIntentCreationService;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use HiEvents\Values\MoneyValue;
use Stripe\Exception\ApiErrorException;
use Throwable;

readonly class CreatePaymentIntentHandler
{
    public function __construct(
        private OrderRepositoryInterface           $orderRepository,
        private StripePaymentIntentCreationService $stripePaymentService,
        private CheckoutSessionManagementService   $sessionIdentifierService,
        private StripePaymentsRepositoryInterface  $stripePaymentsRepository,
        private AccountRepositoryInterface         $accountRepository,
        private StripeClientFactory                $stripeClientFactory,
        private StripeConfigurationService         $stripeConfigurationService,
    )
    {
    }

    /**
     * @param string $orderShortId
     * @return CreatePaymentIntentResponseDTO
     * @throws CreatePaymentIntentFailedException
     * @throws MathException
     * @throws NumberFormatException
     * @throws RoundingNecessaryException
     * @throws UnknownCurrencyException
     * @throws ApiErrorException
     * @throws Throwable
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

        if ($order->getStatus() !== OrderStatus::RESERVED->name || $order->isReservedOrderExpired()) {
            throw new ResourceConflictException(__('Sorry, is expired or not in a valid state.'));
        }

        $account = $this->accountRepository
            ->loadRelation(new Relationship(
                domainObject: AccountConfigurationDomainObject::class,
                name: 'configuration',
            ))
            ->loadRelation(AccountStripePlatformDomainObject::class)
            ->findByEventId($order->getEventId());

        $stripePlatform = $account->getActiveStripePlatform()
            ?? $this->stripeConfigurationService->getPrimaryPlatform();

        $stripeAccountId = $account->getActiveStripeAccountId();

        // If no platform is configured, we can still process payments with regular Stripe keys
        if (!$stripePlatform) {
            $stripePlatform = null; // This will use default keys in StripeClientFactory
        }

        $stripeClient = $this->stripeClientFactory->createForPlatform($stripePlatform);
        $publicKey = $this->stripeConfigurationService->getPublicKey($stripePlatform);

        // If we already have a Stripe session then re-fetch the client secret
        if ($order->getStripePayment() !== null) {
            return new CreatePaymentIntentResponseDTO(
                paymentIntentId: $order->getStripePayment()->getPaymentIntentId(),
                clientSecret: $this->stripePaymentService->retrievePaymentIntentClientSecretWithClient(
                    $stripeClient,
                    $order->getStripePayment()->getPaymentIntentId(),
                    $stripeAccountId
                ),
                accountId: $stripeAccountId,
                stripePlatform: $stripePlatform,
                publicKey: $publicKey,
            );
        }

        $paymentIntent = $this->stripePaymentService->createPaymentIntentWithClient(
            $stripeClient,
            CreatePaymentIntentRequestDTO::fromArray([
                'amount' => MoneyValue::fromFloat($order->getTotalGross(), $order->getCurrency()),
                'currencyCode' => $order->getCurrency(),
                'account' => $account,
                'order' => $order,
                'stripeAccountId' => $stripeAccountId,
            ])
        );

        $this->stripePaymentsRepository->create([
            StripePaymentDomainObjectAbstract::ORDER_ID => $order->getId(),
            StripePaymentDomainObjectAbstract::PAYMENT_INTENT_ID => $paymentIntent->paymentIntentId,
            StripePaymentDomainObjectAbstract::CONNECTED_ACCOUNT_ID => $stripeAccountId,
            StripePaymentDomainObjectAbstract::APPLICATION_FEE => $paymentIntent->applicationFeeAmount,
            StripePaymentDomainObjectAbstract::STRIPE_PLATFORM => $stripePlatform?->value,
        ]);

        return new CreatePaymentIntentResponseDTO(
            paymentIntentId: $paymentIntent->paymentIntentId,
            clientSecret: $paymentIntent->clientSecret,
            accountId: $paymentIntent->accountId,
            applicationFeeAmount: $paymentIntent->applicationFeeAmount,
            stripePlatform: $stripePlatform,
            publicKey: $publicKey,
        );
    }
}

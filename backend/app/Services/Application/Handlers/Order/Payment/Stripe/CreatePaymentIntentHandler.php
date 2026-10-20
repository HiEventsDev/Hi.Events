<?php

namespace HiEvents\Services\Application\Handlers\Order\Payment\Stripe;

use Brick\Math\Exception\MathException;
use Brick\Math\Exception\NumberFormatException;
use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Exception\UnknownCurrencyException;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\StripePaymentDomainObjectAbstract;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\DomainObjects\OrganizerStripePlatformDomainObject;
use HiEvents\DomainObjects\OrganizerVatSettingDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\Stripe\CreatePaymentIntentFailedException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Repository\Interfaces\StripePaymentsRepositoryInterface;
use HiEvents\Services\Domain\Payment\Stripe\DTOs\CreatePaymentIntentRequestDTO;
use HiEvents\Services\Domain\Payment\Stripe\DTOs\CreatePaymentIntentResponseDTO;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentIntentCreationService;
use HiEvents\Services\Infrastructure\Session\CheckoutSessionManagementService;
use HiEvents\Services\Infrastructure\Stripe\StripeClientFactory;
use HiEvents\Services\Infrastructure\Stripe\StripeConfigurationService;
use HiEvents\Values\MoneyValue;
use Illuminate\Support\Str;
use Stripe\Exception\ApiErrorException;
use Throwable;

readonly class CreatePaymentIntentHandler
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository,
        private StripePaymentIntentCreationService $stripePaymentService,
        private CheckoutSessionManagementService $sessionIdentifierService,
        private StripePaymentsRepositoryInterface $stripePaymentsRepository,
        private AccountRepositoryInterface $accountRepository,
        private OrganizerRepositoryInterface $organizerRepository,
        private StripeClientFactory $stripeClientFactory,
        private StripeConfigurationService $stripeConfigurationService,
    ) {}

    /**
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
            ->loadRelation(new Relationship(EventDomainObject::class, name: 'event'))
            ->findByShortId($orderShortId);

        if (! $order || ! $this->sessionIdentifierService->verifySession($order->getSessionId())) {
            throw new UnauthorizedException(__('Sorry, we could not verify your session. Please create a new order.'));
        }

        if ($order->getStatus() !== OrderStatus::RESERVED->name || $order->isReservedOrderExpired()) {
            throw new ResourceConflictException(__('Sorry, is expired or not in a valid state.'));
        }

        $event = $order->getEvent();

        $organizer = $this->organizerRepository
            ->loadRelation(OrganizerStripePlatformDomainObject::class)
            ->loadRelation(new Relationship(
                domainObject: OrganizerConfigurationDomainObject::class,
                name: 'organizer_configuration',
            ))
            ->loadRelation(new Relationship(
                domainObject: OrganizerVatSettingDomainObject::class,
                name: 'organizer_vat_setting',
            ))
            ->findById($event->getOrganizerId());

        $account = $this->accountRepository->findByEventId($order->getEventId());

        $stripePlatform = $organizer?->getActiveStripePlatform()
            ?? $this->stripeConfigurationService->getPrimaryPlatform();

        $stripeAccountId = $organizer?->getActiveStripeAccountId();

        $stripeClient = $this->stripeClientFactory->createForPlatform($stripePlatform);
        $publicKey = $this->stripeConfigurationService->getPublicKey($stripePlatform);

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

        $description = __(':item_count item(s) for event: :event_name (Order :order_short_id)', [
            'event_name' => Str::limit($order->getEvent()?->getTitle() ?? __('Event'), 75),
            'order_short_id' => $orderShortId,
            'item_count' => $order->getOrderItems()->sum(fn (OrderItemDomainObject $item) => $item->getQuantity()),
        ]);

        $paymentIntent = $this->stripePaymentService->createPaymentIntentWithClient(
            $stripeClient,
            CreatePaymentIntentRequestDTO::fromArray([
                'amount' => MoneyValue::fromFloat($order->getTotalGross(), $order->getCurrency()),
                'currencyCode' => $order->getCurrency(),
                'account' => $account,
                'order' => $order,
                'configuration' => $organizer?->getOrganizerConfiguration(),
                'stripeAccountId' => $stripeAccountId,
                'vatSettings' => $organizer?->getOrganizerVatSetting(),
                'description' => Str::limit($description, 997),
            ])
        );

        $applicationFeeData = $paymentIntent->applicationFeeData;

        $this->stripePaymentsRepository->create([
            StripePaymentDomainObjectAbstract::ORDER_ID => $order->getId(),
            StripePaymentDomainObjectAbstract::PAYMENT_INTENT_ID => $paymentIntent->paymentIntentId,
            StripePaymentDomainObjectAbstract::CONNECTED_ACCOUNT_ID => $stripeAccountId,
            StripePaymentDomainObjectAbstract::APPLICATION_FEE_GROSS => $applicationFeeData?->grossApplicationFee?->toMinorUnit() ?? 0,
            StripePaymentDomainObjectAbstract::APPLICATION_FEE_NET => $applicationFeeData?->netApplicationFee?->toMinorUnit() ?? 0,
            StripePaymentDomainObjectAbstract::APPLICATION_FEE_VAT => $applicationFeeData?->applicationFeeVatAmount?->toMinorUnit() ?? 0,
            StripePaymentDomainObjectAbstract::APPLICATION_FEE_VAT_RATE => $applicationFeeData?->applicationFeeVatRate,
            StripePaymentDomainObjectAbstract::CURRENCY => strtoupper($order->getCurrency()),
            StripePaymentDomainObjectAbstract::STRIPE_PLATFORM => $stripePlatform?->value,
        ]);

        return new CreatePaymentIntentResponseDTO(
            paymentIntentId: $paymentIntent->paymentIntentId,
            clientSecret: $paymentIntent->clientSecret,
            accountId: $paymentIntent->accountId,
            applicationFeeData: $paymentIntent->applicationFeeData,
            stripePlatform: $stripePlatform,
            publicKey: $publicKey,
        );
    }
}

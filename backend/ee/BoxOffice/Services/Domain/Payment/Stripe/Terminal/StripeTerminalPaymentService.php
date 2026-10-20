<?php

namespace HiEvents\Enterprise\BoxOffice\Services\Domain\Payment\Stripe\Terminal;

use Carbon\Carbon;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\BoxOfficeTender;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\StripePaymentDomainObjectAbstract;
use HiEvents\DomainObjects\LocationDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\DomainObjects\OrganizerStripePlatformDomainObject;
use HiEvents\DomainObjects\OrganizerVatSettingDomainObject;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\DomainObjects\StripeTerminalReaderDomainObject;
use HiEvents\Enterprise\BoxOffice\Exceptions\BoxOfficeSaleExpiredException;
use HiEvents\Enterprise\BoxOffice\Exceptions\Stripe\TerminalLocationAddressMissingException;
use HiEvents\Enterprise\BoxOffice\Exceptions\Stripe\TerminalReaderUnavailableException;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\StripeTerminalReaderRepositoryInterface;
use HiEvents\Enterprise\BoxOffice\Services\Domain\BoxOfficeOrderCreationService;
use HiEvents\Exceptions\CannotAcceptPaymentException;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\Stripe\CreatePaymentIntentFailedException;
use HiEvents\Exceptions\Stripe\StripeClientConfigurationException;
use HiEvents\Repository\Eloquent\StripePaymentsRepository;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AccountRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Domain\Payment\Stripe\DTOs\CreatePaymentIntentRequestDTO;
use HiEvents\Services\Domain\Payment\Stripe\EventHandlers\PaymentIntentSucceededHandler;
use HiEvents\Services\Domain\Payment\Stripe\StripePaymentIntentCreationService;
use HiEvents\Services\Domain\Payment\Stripe\StripeRefundExpiredOrderService;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use HiEvents\Values\MoneyValue;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;
use Stripe\Terminal\Reader;
use Throwable;

class StripeTerminalPaymentService
{
    private const READER_ERROR_CODES = ['terminal_reader_offline', 'terminal_reader_busy', 'terminal_reader_timeout', 'terminal_reader_hardware_fault'];

    public const CODE_PAYMENT_REJECTED = 'payment_rejected';

    private const REUSABLE_INTENT_STATUSES = [PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD, PaymentIntent::STATUS_REQUIRES_ACTION];

    private const MAX_SALE_HOLD_MINUTES = 120;

    public function __construct(
        private readonly DatabaseManager $databaseManager,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly StripeTerminalReaderRepositoryInterface $readerRepository,
        private readonly StripePaymentsRepository $stripePaymentsRepository,
        private readonly StripePaymentIntentCreationService $paymentIntentCreationService,
        private readonly StripeTerminalContextService $contextService,
        private readonly StripeTerminalLocationService $locationService,
        private readonly PaymentIntentSucceededHandler $paymentIntentSucceededHandler,
        private readonly TerminalFailureResolver $failureResolver,
        private readonly TerminalAttemptTracker $attemptTracker,
        private readonly Cache $cache,
        private readonly TransactionLockService $transactionLockService,
        private readonly StripeRefundExpiredOrderService $refundExpiredOrderService,
    ) {}

    /**
     * @throws ResourceConflictException
     * @throws BoxOfficeSaleExpiredException
     * @throws TerminalReaderUnavailableException
     * @throws CreatePaymentIntentFailedException
     * @throws StripeClientConfigurationException
     * @throws Throwable
     */
    public function ensureProcessing(OrderDomainObject $order, EventDomainObject $event, int $readerId): OrderDomainObject
    {
        $reader = $this->resolveReader($event, $readerId);
        $organizer = $this->loadOrganizer($event->getOrganizerId());
        $context = $this->contextService->forOrganizer($organizer);

        $paymentIntent = $this->databaseManager->transaction(function () use ($order, $event, $organizer, $context) {
            $this->transactionLockService->lockOrder($order->getShortId());

            $order = $this->reload($order);
            $this->assertPayable($order);

            $stripePayment = $order->getStripePayment();
            $paymentIntent = $stripePayment === null
                ? null
                : $this->retrieveIntent($context, $stripePayment->getPaymentIntentId());

            if ($paymentIntent === null || $paymentIntent->status === PaymentIntent::STATUS_CANCELED) {
                $paymentIntent = $this->createIntent($order, $event, $organizer, $context);
            }

            $this->orderRepository->updateFromArray($order->getId(), [
                OrderDomainObjectAbstract::BOX_OFFICE_TENDER => BoxOfficeTender::CARD->value,
                OrderDomainObjectAbstract::RESERVED_UNTIL => $this->cardHoldUntil($order),
            ]);

            return $paymentIntent;
        });

        if ($paymentIntent->status === PaymentIntent::STATUS_SUCCEEDED) {
            $this->completeFromIntent($paymentIntent);

            return $this->reload($order);
        }

        if (in_array($paymentIntent->status, self::REUSABLE_INTENT_STATUSES, true)) {
            $liveReader = $this->assertReaderOnLocation($context, $reader, $this->syncLocation($organizer, $context));

            if ($liveReader->action?->status === 'in_progress' && $this->actionIntentId($liveReader) === $paymentIntent->id) {
                return $this->reload($order);
            }

            $this->clearStaleReaderAction($context, $reader, $liveReader, $paymentIntent);
            $this->stripePaymentsRepository->updateWhere(
                attributes: [StripePaymentDomainObjectAbstract::LAST_ERROR => null],
                where: [StripePaymentDomainObjectAbstract::PAYMENT_INTENT_ID => $paymentIntent->id],
            );
            $this->attemptTracker->start($paymentIntent);
            $this->processOnReader($context, $reader, $paymentIntent);
        }

        return $this->reload($order);
    }

    private function cardHoldUntil(OrderDomainObject $order): string
    {
        return Carbon::now()->addMinutes(BoxOfficeOrderCreationService::RESERVATION_MINUTES)
            ->min(Carbon::parse($order->getCreatedAt())->addMinutes(self::MAX_SALE_HOLD_MINUTES))
            ->toDateTimeString();
    }

    /**
     * @throws TerminalReaderUnavailableException
     */
    private function clearStaleReaderAction(DTO\TerminalContextDTO $context, StripeTerminalReaderDomainObject $reader, Reader $liveReader, PaymentIntent $paymentIntent): void
    {
        $actionIntentId = $this->actionIntentId($liveReader);

        if ($liveReader->action?->status !== 'in_progress' || $actionIntentId === $paymentIntent->id) {
            return;
        }

        if ($actionIntentId !== null && $this->isLiveSale($actionIntentId)) {
            throw new TerminalReaderUnavailableException(__('This card reader is taking a payment for another sale. Wait for it to finish, or choose another reader.'));
        }

        try {
            $context->client->terminal->readers->cancelAction($reader->getStripeReaderId(), [], $context->requestOptions());
        } catch (ApiErrorException) {
        }
    }

    private function actionIntentId(Reader $liveReader): ?string
    {
        $actionIntent = $liveReader->action?->process_payment_intent?->payment_intent;

        return is_object($actionIntent) ? $actionIntent->id : $actionIntent;
    }

    private function isLiveSale(string $paymentIntentId): bool
    {
        $order = $this->stripePaymentsRepository
            ->loadRelation(new Relationship(OrderDomainObject::class, name: 'order'))
            ->findFirstWhere([StripePaymentDomainObjectAbstract::PAYMENT_INTENT_ID => $paymentIntentId])
            ?->getOrder();

        return $order !== null && $order->isOrderReserved() && ! $order->isReservedOrderExpired();
    }

    /**
     * @throws ResourceConflictException
     */
    private function syncLocation($organizer, DTO\TerminalContextDTO $context): string
    {
        try {
            return $this->locationService->resolve($organizer, $context);
        } catch (TerminalLocationAddressMissingException $exception) {
            throw new ResourceConflictException($exception->getMessage());
        } catch (ApiErrorException $exception) {
            throw new ResourceConflictException(__('Unable to update the reader location: :message', ['message' => $exception->getMessage()]));
        }
    }

    /**
     * @throws TerminalReaderUnavailableException
     * @throws ResourceConflictException
     */
    private function assertReaderOnLocation(DTO\TerminalContextDTO $context, StripeTerminalReaderDomainObject $reader, string $locationId): Reader
    {
        try {
            $live = $context->client->terminal->readers->retrieve($reader->getStripeReaderId(), [], $context->requestOptions());
        } catch (ApiErrorException $exception) {
            throw new ResourceConflictException(__('Unable to reach Stripe: :message', ['message' => $exception->getMessage()]));
        }

        $readerLocationId = is_object($live->location) ? $live->location->id : $live->location;

        if ($readerLocationId !== $locationId) {
            throw new TerminalReaderUnavailableException(__('This reader is registered to an old address. Remove it under Settings › Card readers and pair it again.'));
        }

        return $live;
    }

    /**
     * @throws StripeClientConfigurationException
     * @throws ResourceConflictException
     * @throws Throwable
     */
    public function syncFromStripe(OrderDomainObject $order, EventDomainObject $event, ?int $readerId = null): OrderDomainObject
    {
        $stripePayment = $order->getStripePayment();

        if ($stripePayment === null || ! $order->isOrderReserved()) {
            return $order;
        }

        $context = $this->contextService->forOrganizer($this->loadOrganizer($event->getOrganizerId()));
        $paymentIntent = $this->retrieveIntent($context, $stripePayment->getPaymentIntentId(), expandCharge: true);

        if ($paymentIntent->status === PaymentIntent::STATUS_SUCCEEDED) {
            $this->completeFromIntent($paymentIntent);

            return $this->reload($order);
        }

        $failure = $this->failureResolver->fromPaymentIntent($paymentIntent, $this->attemptTracker->acknowledgedChargeId($paymentIntent->id))
            ?? $this->readerFailure($context, $readerId, $paymentIntent->id);

        if ($failure !== null && $failure !== $stripePayment->getLastError()) {
            $this->stripePaymentsRepository->updateWhere(
                attributes: [StripePaymentDomainObjectAbstract::LAST_ERROR => $failure],
                where: [StripePaymentDomainObjectAbstract::ID => $stripePayment->getId()],
            );

            return $this->reload($order);
        }

        return $order;
    }

    private function readerFailure(DTO\TerminalContextDTO $context, ?int $readerId, string $paymentIntentId): ?array
    {
        if ($readerId === null) {
            return null;
        }

        $reader = $this->readerRepository->findFirstWhere(['id' => $readerId]);

        if ($reader === null) {
            return null;
        }

        try {
            $stripeReader = $context->client->terminal->readers->retrieve($reader->getStripeReaderId(), [], $context->requestOptions());
        } catch (ApiErrorException) {
            return null;
        }

        return $this->failureResolver->fromReader($stripeReader, $paymentIntentId);
    }

    /**
     * @throws StripeClientConfigurationException
     */
    public function cancelReaderActionFor(EventDomainObject $event, int $readerId, string $paymentIntentId): void
    {
        $reader = $this->readerRepository->findFirstWhere(['id' => $readerId]);

        if ($reader === null) {
            return;
        }

        $context = $this->contextService->forOrganizer($this->loadOrganizer($event->getOrganizerId()));

        try {
            $liveReader = $context->client->terminal->readers->retrieve($reader->getStripeReaderId(), [], $context->requestOptions());

            if ($this->actionIntentId($liveReader) !== $paymentIntentId) {
                return;
            }

            $context->client->terminal->readers->cancelAction($reader->getStripeReaderId(), [], $context->requestOptions());
        } catch (ApiErrorException) {
        }
    }

    /**
     * @throws ResourceConflictException
     * @throws StripeClientConfigurationException
     * @throws Throwable
     */
    public function releaseCardPayment(OrderDomainObject $order, EventDomainObject $event, ?int $readerId): ?string
    {
        $order = $this->reload($order);
        $stripePayment = $order->getStripePayment();

        if ($stripePayment === null) {
            return null;
        }

        if ($readerId !== null) {
            $this->cancelReaderActionFor($event, $readerId, $stripePayment->getPaymentIntentId());
        }

        $context = $this->contextService->forOrganizer($this->loadOrganizer($event->getOrganizerId()));
        $paymentIntent = $this->retrieveIntent($context, $stripePayment->getPaymentIntentId());

        if ($paymentIntent->status === PaymentIntent::STATUS_SUCCEEDED) {
            $this->assertRefundedAfterSucceeding($paymentIntent);

            return $paymentIntent->id;
        }

        if ($paymentIntent->status === PaymentIntent::STATUS_PROCESSING) {
            throw new ResourceConflictException(__('A card payment is still in progress on the reader. Wait for it to finish or cancel it first.'));
        }

        if ($paymentIntent->status !== PaymentIntent::STATUS_CANCELED) {
            try {
                $context->client->paymentIntents->cancel($paymentIntent->id, [], $context->requestOptions());
            } catch (ApiErrorException) {
                $paymentIntent = $this->retrieveIntent($context, $paymentIntent->id);

                if ($paymentIntent->status === PaymentIntent::STATUS_SUCCEEDED) {
                    $this->assertRefundedAfterSucceeding($paymentIntent);

                    return $paymentIntent->id;
                }

                if ($paymentIntent->status !== PaymentIntent::STATUS_CANCELED) {
                    throw new ResourceConflictException(__('The card payment could not be cancelled. Try again in a moment.'));
                }
            }
        }

        return $paymentIntent->id;
    }

    /**
     * @throws TerminalReaderUnavailableException
     */
    private function resolveReader(EventDomainObject $event, int $readerId): StripeTerminalReaderDomainObject
    {
        $reader = $this->readerRepository->findFirstWhere([
            'id' => $readerId,
            'organizer_id' => $event->getOrganizerId(),
        ]);

        if ($reader === null) {
            throw new TerminalReaderUnavailableException(__('This card reader has been removed. Pick another reader or use cash.'));
        }

        return $reader;
    }

    private function loadOrganizer(int $organizerId)
    {
        return $this->organizerRepository
            ->loadRelation(OrganizerStripePlatformDomainObject::class)
            ->loadRelation(new Relationship(domainObject: OrganizerConfigurationDomainObject::class, name: 'organizer_configuration'))
            ->loadRelation(new Relationship(domainObject: OrganizerVatSettingDomainObject::class, name: 'organizer_vat_setting'))
            ->loadRelation(new Relationship(domainObject: LocationDomainObject::class, name: 'location_record'))
            ->findById($organizerId);
    }

    private function reload(OrderDomainObject $order): OrderDomainObject
    {
        return $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->loadRelation(AttendeeDomainObject::class)
            ->loadRelation(new Relationship(StripePaymentDomainObject::class, name: 'stripe_payment'))
            ->findById($order->getId());
    }

    /**
     * @throws ResourceConflictException
     * @throws BoxOfficeSaleExpiredException
     */
    private function assertPayable(OrderDomainObject $order): void
    {
        if (! $order->isOrderReserved()) {
            throw new ResourceConflictException(__('This sale has already been completed or cancelled'));
        }

        if ($order->getReservedUntil() !== null && Carbon::parse($order->getReservedUntil())->isPast()) {
            throw new BoxOfficeSaleExpiredException(__('This sale has expired. Start a new sale.'));
        }

        if ((float) $order->getTotalGross() <= 0) {
            throw new ResourceConflictException(__('There is nothing to charge for this sale'));
        }
    }

    /**
     * @throws CreatePaymentIntentFailedException
     * @throws Throwable
     */
    private function createIntent(OrderDomainObject $order, EventDomainObject $event, $organizer, DTO\TerminalContextDTO $context): PaymentIntent
    {
        $account = $this->accountRepository->findByEventId($order->getEventId());

        $response = $this->paymentIntentCreationService->createTerminalPaymentIntentWithClient(
            $context->client,
            CreatePaymentIntentRequestDTO::fromArray([
                'amount' => MoneyValue::fromFloat($order->getTotalGross(), $order->getCurrency()),
                'currencyCode' => $order->getCurrency(),
                'account' => $account,
                'order' => $order,
                'configuration' => $organizer?->getOrganizerConfiguration(),
                'stripeAccountId' => $context->stripe_account_id,
                'vatSettings' => $organizer?->getOrganizerVatSetting(),
                'description' => Str::limit(__('Box office sale for :event_name (Order :order_short_id)', [
                    'event_name' => Str::limit($event->getTitle(), 75),
                    'order_short_id' => $order->getShortId(),
                ]), 997),
            ]),
        );

        $applicationFee = $response->applicationFeeData;

        if ($order->getStripePayment() !== null) {
            $this->stripePaymentsRepository->deleteWhere([StripePaymentDomainObjectAbstract::ID => $order->getStripePayment()->getId()]);
        }

        $this->stripePaymentsRepository->create([
            StripePaymentDomainObjectAbstract::ORDER_ID => $order->getId(),
            StripePaymentDomainObjectAbstract::PAYMENT_INTENT_ID => $response->paymentIntentId,
            StripePaymentDomainObjectAbstract::CONNECTED_ACCOUNT_ID => $context->stripe_account_id,
            StripePaymentDomainObjectAbstract::APPLICATION_FEE_GROSS => $applicationFee?->grossApplicationFee?->toMinorUnit() ?? 0,
            StripePaymentDomainObjectAbstract::APPLICATION_FEE_NET => $applicationFee?->netApplicationFee?->toMinorUnit() ?? 0,
            StripePaymentDomainObjectAbstract::APPLICATION_FEE_VAT => $applicationFee?->applicationFeeVatAmount?->toMinorUnit() ?? 0,
            StripePaymentDomainObjectAbstract::APPLICATION_FEE_VAT_RATE => $applicationFee?->applicationFeeVatRate,
            StripePaymentDomainObjectAbstract::CURRENCY => $order->getCurrency(),
            StripePaymentDomainObjectAbstract::STRIPE_PLATFORM => $context->platform?->value,
        ]);

        return $this->retrieveIntent($context, $response->paymentIntentId);
    }

    /**
     * @throws ResourceConflictException
     */
    private function retrieveIntent(DTO\TerminalContextDTO $context, string $paymentIntentId, bool $expandCharge = false): PaymentIntent
    {
        try {
            return $context->client->paymentIntents->retrieve(
                $paymentIntentId,
                $expandCharge ? ['expand' => ['latest_charge']] : [],
                $context->requestOptions(),
            );
        } catch (ApiErrorException $exception) {
            throw new ResourceConflictException(__('Unable to reach Stripe: :message', ['message' => $exception->getMessage()]));
        }
    }

    /**
     * @throws TerminalReaderUnavailableException
     * @throws ResourceConflictException
     */
    private function processOnReader(DTO\TerminalContextDTO $context, StripeTerminalReaderDomainObject $reader, PaymentIntent $paymentIntent): void
    {
        try {
            $context->client->terminal->readers->processPaymentIntent($reader->getStripeReaderId(), [
                'payment_intent' => $paymentIntent->id,
                'process_config' => ['enable_customer_cancellation' => true, 'skip_tipping' => true],
            ], $context->requestOptions());
        } catch (ApiErrorException $exception) {
            $code = $exception->getStripeCode() ?? '';

            if (in_array($code, self::READER_ERROR_CODES, true)) {
                throw new TerminalReaderUnavailableException(__('The card reader is not responding. Check it is powered on and connected, then try again.'));
            }

            throw new ResourceConflictException($exception->getMessage());
        }
    }

    /**
     * @throws Throwable
     */
    /**
     * @throws ResourceConflictException
     * @throws Throwable
     */
    private function assertRefundedAfterSucceeding(PaymentIntent $paymentIntent): void
    {
        $this->completeFromIntent($paymentIntent);

        if (! $this->wasRefunded($paymentIntent->id)) {
            throw new ResourceConflictException(__('The card payment already went through. This sale is complete.'));
        }
    }

    private function wasRefunded(string $paymentIntentId): bool
    {
        $stripePayment = $this->stripePaymentsRepository
            ->findFirstWhere([StripePaymentDomainObjectAbstract::PAYMENT_INTENT_ID => $paymentIntentId]);

        return $stripePayment !== null
            && $this->refundExpiredOrderService->hasRefunded($stripePayment->getOrderId(), $paymentIntentId);
    }

    private function completeFromIntent(PaymentIntent $paymentIntent): void
    {
        $lock = $this->cache->lock('box_office_card_'.$paymentIntent->id, 10);

        if (! $lock->get()) {
            return;
        }

        try {
            if ($this->wasRefunded($paymentIntent->id)) {
                $this->markRejected($paymentIntent->id, __('The card payment was refunded because this sale could no longer be completed.'));

                return;
            }

            $this->paymentIntentSucceededHandler->handleEvent($paymentIntent);
        } catch (CannotAcceptPaymentException $exception) {
            $this->markRejected($paymentIntent->id, $exception->getMessage());
        } finally {
            $lock->release();
        }
    }

    private function markRejected(string $paymentIntentId, string $message): void
    {
        $this->stripePaymentsRepository->updateWhere(
            attributes: [StripePaymentDomainObjectAbstract::LAST_ERROR => [
                'code' => self::CODE_PAYMENT_REJECTED,
                'message' => $message,
                'charge_id' => null,
            ]],
            where: [StripePaymentDomainObjectAbstract::PAYMENT_INTENT_ID => $paymentIntentId],
        );
    }
}

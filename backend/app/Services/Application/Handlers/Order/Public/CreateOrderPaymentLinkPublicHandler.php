<?php

namespace HiEvents\Services\Application\Handlers\Order\Public;

use Carbon\Carbon;
use HiEvents\DomainObjects\Enums\OrderCheckoutAccess;
use HiEvents\DomainObjects\Enums\PaymentProviders;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\WaitlistEntryDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Exceptions\UnauthorizedException;
use HiEvents\Mail\Order\OrderPaymentLinkMail;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use HiEvents\Services\Domain\Order\PaymentLink\OrderCheckoutAccessService;
use HiEvents\Services\Domain\Order\PaymentLink\OrderPaymentLinkTokenService;
use HiEvents\Services\Infrastructure\Lock\TransactionLockService;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\DatabaseManager;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class CreateOrderPaymentLinkPublicHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly EventOccurrenceRepositoryInterface $eventOccurrenceRepository,
        private readonly WaitlistEntryRepositoryInterface $waitlistEntryRepository,
        private readonly OrderCheckoutAccessService $checkoutAccessService,
        private readonly OrderPaymentLinkTokenService $paymentLinkTokenService,
        private readonly TransactionLockService $transactionLockService,
        private readonly DatabaseManager $databaseManager,
        private readonly Mailer $mailer,
    ) {}

    /**
     * @throws ResourceConflictException
     * @throws UnauthorizedException
     */
    public function handle(int $eventId, string $orderShortId): OrderDomainObject
    {
        return $this->databaseManager->transaction(function () use ($eventId, $orderShortId) {
            $this->transactionLockService->lockOrder($orderShortId);

            $order = $this->findOrder($eventId, $orderShortId);

            $event = $this->eventRepository
                ->loadRelation(new Relationship(OrganizerDomainObject::class, name: 'organizer'))
                ->loadRelation(new Relationship(EventSettingDomainObject::class))
                ->findById($eventId);

            if ($order->isOrderAwaitingOfflinePayment()) {
                $this->validatePlacedOrder($order, $event->getEventSettings());

                if (! $order->hasPaymentLink()) {
                    $order = $this->createLinkForPlacedOrder($order);
                }
            } else {
                if ($this->checkoutAccessService->resolve($order) !== OrderCheckoutAccess::OWNER) {
                    throw new UnauthorizedException(__('Sorry, we could not verify your session. Please restart your order.'));
                }

                $this->validateReservedOrder($order, $event->getEventSettings());

                if (! $order->hasPaymentLink()) {
                    $order = $this->createLinkForReservedOrder($order, $event);
                }
            }

            return $order
                ->setPaymentLinkUrl($this->paymentLinkTokenService->urlFor($order));
        });
    }

    private function findOrder(int $eventId, string $orderShortId): OrderDomainObject
    {
        $order = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->findFirstWhere([
                OrderDomainObjectAbstract::EVENT_ID => $eventId,
                OrderDomainObjectAbstract::SHORT_ID => $orderShortId,
            ]);

        if ($order === null) {
            throw new ResourceNotFoundException(__('Order not found'));
        }

        return $order;
    }

    /**
     * @throws ResourceConflictException
     */
    private function validatePlacedOrder(OrderDomainObject $order, EventSettingDomainObject $settings): void
    {
        $this->assertLinksAvailable($settings);

        if (! $order->isPaymentRequired()) {
            throw new ResourceConflictException(__('This order does not require payment'));
        }
    }

    /**
     * @throws ResourceConflictException
     */
    private function validateReservedOrder(OrderDomainObject $order, EventSettingDomainObject $settings): void
    {
        $this->assertLinksAvailable($settings);

        if (! collect($settings->getPaymentProviders())->contains(PaymentProviders::STRIPE->value)) {
            throw new ResourceConflictException(__('Payment links are not available for this event'));
        }

        if (! $order->isOrderReserved() || $order->isReservedOrderExpired()) {
            throw new ResourceConflictException(__('This order has expired. Please start a new order.'));
        }

        if (! $order->isPaymentRequired()) {
            throw new ResourceConflictException(__('This order does not require payment'));
        }

        if ($order->getEmail() === null) {
            throw new ResourceConflictException(__('Please complete your details before creating a payment link'));
        }
    }

    /**
     * @throws ResourceConflictException
     */
    private function assertLinksAvailable(EventSettingDomainObject $settings): void
    {
        $offlineEnabled = collect($settings->getPaymentProviders())->contains(PaymentProviders::OFFLINE->value);

        if (! $offlineEnabled || ! $settings->getPaymentLinksEnabled()) {
            throw new ResourceConflictException(__('Payment links are not available for this event'));
        }
    }

    private function createLinkForPlacedOrder(OrderDomainObject $order): OrderDomainObject
    {
        $this->orderRepository->updateFromArray($order->getId(), [
            OrderDomainObjectAbstract::PAYMENT_LINK_CREATED_AT => Carbon::now('UTC')->startOfSecond()->toDateTimeString(),
        ]);

        return $this->orderRepository->findById($order->getId());
    }

    private function createLinkForReservedOrder(OrderDomainObject $order, EventDomainObject $event): OrderDomainObject
    {
        $this->orderRepository->updateFromArray($order->getId(), [
            OrderDomainObjectAbstract::PAYMENT_LINK_CREATED_AT => Carbon::now('UTC')->startOfSecond()->toDateTimeString(),
            OrderDomainObjectAbstract::RESERVED_UNTIL => $this->holdUntil($order, $event->getEventSettings())->toDateTimeString(),
        ]);

        $order = $this->orderRepository
            ->loadRelation(OrderItemDomainObject::class)
            ->findById($order->getId());

        $this->mailer
            ->to($order->getEmail())
            ->locale($order->getLocale())
            ->send(new OrderPaymentLinkMail(
                order: $order,
                event: $event,
                organizer: $event->getOrganizer(),
                eventSettings: $event->getEventSettings(),
                paymentLinkUrl: $this->paymentLinkTokenService->urlFor($order),
            ));

        return $order;
    }

    private function holdUntil(OrderDomainObject $order, EventSettingDomainObject $settings): Carbon
    {
        $currentHold = Carbon::parse($order->getReservedUntil(), 'UTC');
        $hold = Carbon::now('UTC')->addHours($settings->getPaymentLinkExpiryHours())->startOfSecond();

        $earliestStart = $this->earliestUpcomingOccurrenceStart($order);

        if ($earliestStart !== null && $earliestStart->lt($hold)) {
            $hold = $earliestStart;
        }

        $offerExpiresAt = $this->waitlistOfferExpiry($order);

        if ($offerExpiresAt !== null && $offerExpiresAt->lt($hold)) {
            $hold = $offerExpiresAt;
        }

        return $hold->max($currentHold);
    }

    private function waitlistOfferExpiry(OrderDomainObject $order): ?Carbon
    {
        $offerExpiresAt = $this->waitlistEntryRepository->findFirstWhere([
            WaitlistEntryDomainObjectAbstract::ORDER_ID => $order->getId(),
            WaitlistEntryDomainObjectAbstract::STATUS => WaitlistEntryStatus::OFFERED->name,
        ])?->getOfferExpiresAt();

        return $offerExpiresAt === null ? null : Carbon::parse($offerExpiresAt, 'UTC');
    }

    private function earliestUpcomingOccurrenceStart(OrderDomainObject $order): ?Carbon
    {
        $occurrenceIds = $order->getOrderItems()
            ?->map(fn (OrderItemDomainObject $item) => $item->getEventOccurrenceId())
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($occurrenceIds)) {
            return null;
        }

        return $this->eventOccurrenceRepository
            ->findWhereIn('id', $occurrenceIds)
            ->map(fn (EventOccurrenceDomainObject $occurrence) => Carbon::parse($occurrence->getStartDate(), 'UTC'))
            ->filter(fn (Carbon $start) => $start->isFuture())
            ->sort()
            ->first();
    }
}

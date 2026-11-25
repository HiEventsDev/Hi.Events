<?php

namespace HiEvents\Services\Domain\TicketResale;

use HiEvents\DomainObjects\AttendeeCheckInDomainObject;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventOccurrenceDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\TicketResaleListingDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\TicketTransferDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrderItemDomainObject;
use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\DomainObjects\OrganizerStripePlatformDomainObject;
use HiEvents\DomainObjects\OrganizerVatSettingDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\TicketResaleListingStatus;
use HiEvents\DomainObjects\StripePaymentDomainObject;
use HiEvents\DomainObjects\TaxAndFeesDomainObject;
use HiEvents\DomainObjects\TicketResaleListingDomainObject;
use HiEvents\DomainObjects\TicketTransferDomainObject;
use HiEvents\Enterprise\Seating\Services\Domain\SeatedProductLookupService;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketResaleListingRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketTransferRepositoryInterface;
use HiEvents\Services\Domain\Payment\Stripe\StripeOrderFeeBackfillService;
use HiEvents\Services\Domain\TicketResale\DTO\TicketResaleContextDTO;
use HiEvents\Services\Domain\TicketResale\DTO\TicketResalePaymentFactsDTO;
use HiEvents\Values\MoneyValue;
use Illuminate\Support\Collection;

class TicketResaleContextService
{
    public function __construct(
        private readonly AttendeeRepositoryInterface $attendeeRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly TicketTransferRepositoryInterface $ticketTransferRepository,
        private readonly TicketResaleListingRepositoryInterface $listingRepository,
        private readonly SeatedProductLookupService $seatedProductLookup,
        private readonly TicketResalePaymentFactsService $paymentFactsService,
        private readonly TicketResalePriceFactsService $priceFactsService,
        private readonly StripeOrderFeeBackfillService $feeBackfillService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function forOrderTicketLinkWithRecordedFees(int $eventId, string $orderShortId, string $attendeeShortId): TicketResaleContextDTO
    {
        $context = $this->forOrderTicketLink($eventId, $orderShortId, $attendeeShortId);

        if ($context->payment_fees_recorded || ! $context->paid_online_through_stripe) {
            return $context;
        }

        $this->feeBackfillService->recordIfStripeAllows($context->order->getId());

        return $this->forOrderTicketLink($eventId, $orderShortId, $attendeeShortId);
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function forOrderTicketLink(int $eventId, string $orderShortId, string $attendeeShortId): TicketResaleContextDTO
    {
        $attendee = $this->withRelations()->findFirstWhere([
            AttendeeDomainObjectAbstract::SHORT_ID => $attendeeShortId,
            AttendeeDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($attendee === null || $attendee->getOrder()?->getShortId() !== $orderShortId) {
            throw new ResourceNotFoundException(__('Attendee not found'));
        }

        return $this->contextsFor(collect([$attendee]))->first()
            ?? throw new ResourceNotFoundException(__('Attendee not found'));
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function forAttendeeId(int $attendeeId): TicketResaleContextDTO
    {
        return $this->forAttendeeIds([$attendeeId])->first()
            ?? throw new ResourceNotFoundException(__('Attendee not found'));
    }

    /**
     * @param  int[]  $attendeeIds
     * @return Collection<int, TicketResaleContextDTO> keyed by attendee id
     */
    public function forAttendeeIds(array $attendeeIds): Collection
    {
        if ($attendeeIds === []) {
            return collect();
        }

        return $this->contextsFor(
            $this->withRelations()->findWhereIn(AttendeeDomainObjectAbstract::ID, $attendeeIds)
        );
    }

    private function withRelations(): AttendeeRepositoryInterface
    {
        return $this->attendeeRepository
            ->loadRelation(new Relationship(domainObject: OrderDomainObject::class, nested: [
                new Relationship(domainObject: OrderItemDomainObject::class),
                new Relationship(domainObject: StripePaymentDomainObject::class, name: 'stripe_payment'),
            ], name: 'order'))
            ->loadRelation(new Relationship(domainObject: ProductDomainObject::class, nested: [
                new Relationship(domainObject: ProductPriceDomainObject::class),
                new Relationship(domainObject: TaxAndFeesDomainObject::class),
            ], name: 'product'))
            ->loadRelation(new Relationship(domainObject: EventOccurrenceDomainObject::class, name: 'event_occurrence'))
            ->loadRelation(new Relationship(domainObject: AttendeeCheckInDomainObject::class, name: 'check_ins'));
    }

    /**
     * @param  Collection<int, AttendeeDomainObject>  $attendees
     * @return Collection<int, TicketResaleContextDTO> keyed by attendee id
     */
    private function contextsFor(Collection $attendees): Collection
    {
        $describable = $attendees->filter(
            fn (AttendeeDomainObject $attendee) => $attendee->getOrder() !== null
                && $attendee->getProduct() !== null
                && $attendee->getEventOccurrence() !== null
        );

        if ($describable->isEmpty()) {
            return collect();
        }

        $attendeeIds = $describable->map(fn (AttendeeDomainObject $attendee) => $attendee->getId())->values()->all();

        $events = $describable
            ->map(fn (AttendeeDomainObject $attendee) => $attendee->getEventId())
            ->unique()
            ->mapWithKeys(fn (int $eventId) => [$eventId => $this->eventWithPaymentSetup($eventId)]);

        $paymentFacts = $describable
            ->map(fn (AttendeeDomainObject $attendee) => $attendee->getOrder())
            ->unique(fn (OrderDomainObject $order) => $order->getId())
            ->mapWithKeys(fn (OrderDomainObject $order) => [
                $order->getId() => $this->paymentFactsService->forOrder($order, $events->get($order->getEventId())->getOrganizer()),
            ]);

        $attendeeIdsAwaitingRecipient = $this->ticketTransferRepository
            ->findWhereIn(TicketTransferDomainObjectAbstract::ATTENDEE_ID, $attendeeIds)
            ->filter(fn (TicketTransferDomainObject $transfer) => $transfer->isAwaitingRecipient())
            ->map(fn (TicketTransferDomainObject $transfer) => $transfer->getAttendeeId());

        $listedAttendeeIds = $this->listingRepository
            ->findWhereIn(TicketResaleListingDomainObjectAbstract::ATTENDEE_ID, $attendeeIds, [
                TicketResaleListingDomainObjectAbstract::STATUS => TicketResaleListingStatus::LISTED->name,
            ])
            ->map(fn (TicketResaleListingDomainObject $listing) => $listing->getAttendeeId());

        return $describable->mapWithKeys(fn (AttendeeDomainObject $attendee) => [
            $attendee->getId() => $this->contextFor(
                attendee: $attendee,
                event: $events->get($attendee->getEventId()),
                paymentFacts: $paymentFacts->get($attendee->getOrderId()),
                hasPendingTransfer: $attendeeIdsAwaitingRecipient->contains($attendee->getId()),
                hasOpenListing: $listedAttendeeIds->contains($attendee->getId()),
            ),
        ]);
    }

    private function eventWithPaymentSetup(int $eventId): EventDomainObject
    {
        return $this->eventRepository
            ->loadRelation(EventSettingDomainObject::class)
            ->loadRelation(new Relationship(domainObject: OrganizerDomainObject::class, nested: [
                new Relationship(domainObject: OrganizerStripePlatformDomainObject::class),
                new Relationship(domainObject: OrganizerConfigurationDomainObject::class, name: 'organizer_configuration'),
                new Relationship(domainObject: OrganizerVatSettingDomainObject::class, name: 'organizer_vat_setting'),
            ], name: 'organizer'))
            ->findById($eventId);
    }

    private function contextFor(
        AttendeeDomainObject $attendee,
        EventDomainObject $event,
        TicketResalePaymentFactsDTO $paymentFacts,
        bool $hasPendingTransfer,
        bool $hasOpenListing,
    ): TicketResaleContextDTO {
        $order = $attendee->getOrder();
        $product = $attendee->getProduct();
        $priceFacts = $this->priceFactsService->forTicket($attendee, $product, $order, $event);
        $orderGross = MoneyValue::fromFloat($order->getTotalGross(), $order->getCurrency())->toMinorUnit();

        return new TicketResaleContextDTO(
            event: $event,
            order: $order,
            attendee: $attendee,
            product: $product,
            occurrence: $attendee->getEventOccurrence(),
            is_checked_in: $attendee->isCheckedIn(),
            is_seated: $this->seatedProductLookup->isSeated($product->getId()),
            is_price_on_sale: $priceFacts->is_on_sale,
            has_pending_transfer: $hasPendingTransfer,
            has_open_listing: $hasOpenListing,
            paid_online_through_stripe: $paymentFacts->paid_online_through_stripe,
            paid_on_current_stripe_account: $paymentFacts->paid_on_current_stripe_account,
            payment_fees_recorded: $paymentFacts->payment_fees_recorded,
            settles_in_order_currency: $paymentFacts->settles_in_order_currency,
            has_only_resale_refunds: $paymentFacts->has_only_resale_refunds,
            seller_unit_gross: $priceFacts->seller_unit_gross,
            current_unit_gross: $priceFacts->current_unit_gross,
            seller_unit_price: $priceFacts->seller_unit_price,
            current_unit_price: $priceFacts->current_unit_price,
            seller_order_gross: $orderGross,
            seller_order_unrefunded_balance: $orderGross
                - MoneyValue::fromFloat($order->getTotalRefunded(), $order->getCurrency())->toMinorUnit(),
            seller_order_card_fee: $paymentFacts->card_fee,
            connected_account_id: $paymentFacts->connected_account_id,
        );
    }
}

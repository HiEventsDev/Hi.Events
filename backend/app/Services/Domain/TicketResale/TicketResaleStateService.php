<?php

namespace HiEvents\Services\Domain\TicketResale;

use Carbon\Carbon;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\TicketResaleListingDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\Status\TicketResaleListingStatus;
use HiEvents\DomainObjects\TicketResaleListingDomainObject;
use HiEvents\Repository\Interfaces\OrderRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketResaleListingRepositoryInterface;
use HiEvents\Services\Domain\TicketResale\DTO\TicketResaleContextDTO;
use HiEvents\Services\Domain\TicketResale\DTO\TicketResaleStateDTO;
use HiEvents\Services\Domain\TicketResale\DTO\TicketResaleSummaryDTO;
use Illuminate\Support\Collection;

class TicketResaleStateService
{
    public function __construct(
        private readonly TicketResaleListingRepositoryInterface $listingRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly TicketResaleContextService $contextService,
        private readonly TicketResaleEligibilityService $eligibilityService,
    ) {}

    /**
     * @param  Collection<int, AttendeeDomainObject>  $attendees
     */
    public function describe(Collection $attendees, EventDomainObject $event, bool $viewerHoldsOrderLink): void
    {
        if ($attendees->isEmpty() || $event->getEventSettings() === null) {
            return;
        }

        $states = $this->statesFor(
            $attendees->map(fn (AttendeeDomainObject $attendee) => $attendee->getId())->values()->all(),
            $event,
            $viewerHoldsOrderLink,
        );

        $attendees->each(fn (AttendeeDomainObject $attendee) => $attendee->setResaleState($states->get($attendee->getId())));
    }

    /**
     * @param  Collection<int, AttendeeDomainObject>  $attendees
     */
    public function summarize(Collection $attendees): void
    {
        if ($attendees->isEmpty()) {
            return;
        }

        $listingsByAttendee = $this->listingRepository
            ->findWhereIn(
                TicketResaleListingDomainObjectAbstract::ATTENDEE_ID,
                $attendees->map(fn (AttendeeDomainObject $attendee) => $attendee->getId())->values()->all(),
            )
            ->groupBy(fn (TicketResaleListingDomainObject $listing) => $listing->getAttendeeId());

        $effectiveListings = $this->listingRepository->findEffectiveForAttendees(
            $listingsByAttendee
                ->filter(fn (Collection $listings) => $listings->contains($this->hasStatus(TicketResaleListingStatus::LISTED)))
                ->keys()
                ->all()
        );

        $soldListings = $listingsByAttendee
            ->map(fn (Collection $listings) => $listings->first($this->hasStatus(TicketResaleListingStatus::SOLD)))
            ->filter();

        $replacementOrders = $this->orderRepository
            ->findWhereIn(
                OrderDomainObjectAbstract::ID,
                $soldListings->map(fn (TicketResaleListingDomainObject $listing) => $listing->getSoldOrderId())->filter()->values()->all(),
            )
            ->keyBy(fn (OrderDomainObject $order) => $order->getId());

        $attendees->each(function (AttendeeDomainObject $attendee) use ($effectiveListings, $soldListings, $replacementOrders): void {
            $soldListing = $soldListings->get($attendee->getId());

            $attendee->setResaleSummary(new TicketResaleSummaryDTO(
                status: match (true) {
                    $effectiveListings->has($attendee->getId()) => TicketResaleListingStatus::LISTED->name,
                    $soldListing !== null => TicketResaleListingStatus::SOLD->name,
                    default => null,
                },
                sold_order_id: $soldListing?->getSoldOrderId(),
                sold_order_public_id: $soldListing?->getSoldOrderId() === null
                    ? null
                    : $replacementOrders->get($soldListing->getSoldOrderId())?->getPublicId(),
            ));
        });
    }

    public function forOrderLinkTicket(TicketResaleContextDTO $context): TicketResaleStateDTO
    {
        return $this->statesFor([$context->attendee->getId()], $context->event, viewerHoldsOrderLink: true)
            ->get($context->attendee->getId());
    }

    /**
     * @param  int[]  $attendeeIds
     * @return Collection<int, TicketResaleStateDTO> keyed by attendee id
     */
    private function statesFor(array $attendeeIds, EventDomainObject $event, bool $viewerHoldsOrderLink): Collection
    {
        $listingsByAttendee = $this->listingRepository
            ->findWhereIn(TicketResaleListingDomainObjectAbstract::ATTENDEE_ID, $attendeeIds)
            ->groupBy(fn (TicketResaleListingDomainObject $listing) => $listing->getAttendeeId());

        $effectiveListings = $this->listingRepository->findEffectiveForAttendees(
            $listingsByAttendee
                ->filter(fn (Collection $listings) => $listings->contains($this->hasStatus(TicketResaleListingStatus::LISTED)))
                ->keys()
                ->all()
        );

        $contexts = $this->contextService->forAttendeeIds(
            $viewerHoldsOrderLink && $event->getEventSettings()?->getAllowTicketResale()
                ? $attendeeIds
                : $effectiveListings->keys()->all()
        );

        return collect($attendeeIds)->mapWithKeys(function (int $attendeeId) use ($listingsByAttendee, $effectiveListings, $contexts, $viewerHoldsOrderLink) {
            $effectiveListing = $effectiveListings->get($attendeeId);
            $context = $contexts->get($attendeeId);
            $wasSold = $listingsByAttendee->get($attendeeId, collect())->contains($this->hasStatus(TicketResaleListingStatus::SOLD));

            return [$attendeeId => new TicketResaleStateDTO(
                can_list: $viewerHoldsOrderLink && $context !== null && $this->eligibilityService->blockOnOfferingResale($context) === null,
                status: match (true) {
                    $effectiveListing !== null => TicketResaleListingStatus::LISTED->name,
                    $wasSold => TicketResaleListingStatus::SOLD->name,
                    default => null,
                },
                held_until: $effectiveListing === null ? null : $this->heldUntil($effectiveListing),
                withdrawal_requested: $effectiveListing?->getWithdrawalRequestedAt() !== null,
                cutoff: $context === null ? null : $this->eligibilityService->cutoff($context)->toIso8601String(),
            )];
        });
    }

    private function hasStatus(TicketResaleListingStatus $status): callable
    {
        return fn (TicketResaleListingDomainObject $listing) => $listing->getStatus() === $status->name;
    }

    private function heldUntil(TicketResaleListingDomainObject $listing): ?string
    {
        if ($listing->getClaimedByOrderId() === null || ! $this->listingRepository->hasLiveClaim($listing->getId())) {
            return null;
        }

        return Carbon::parse(
            $this->orderRepository->findById($listing->getClaimedByOrderId())->getReservedUntil(),
            'UTC',
        )->toIso8601String();
    }
}

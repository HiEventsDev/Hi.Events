<?php

namespace HiEvents\Services\Domain\TicketResale;

use Carbon\Carbon;
use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\TicketResaleListingState;
use HiEvents\DomainObjects\Generated\TicketResaleListingDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\TicketResaleListingStatus;
use HiEvents\DomainObjects\TicketResaleListingDomainObject;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\TicketResaleListingRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ResaleListingOverviewService
{
    public function __construct(
        private readonly TicketResaleListingRepositoryInterface $listingRepository,
    ) {}

    public function paginateForEvent(int $eventId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $listings = $this->withRelations()->findByEventId($eventId, $params);

        $this->describe($listings->getCollection());

        return $listings;
    }

    /**
     * @throws ResourceNotFoundException
     */
    public function findForEvent(int $eventId, int $listingId): TicketResaleListingDomainObject
    {
        $listing = $this->withRelations()->findFirstWhere([
            TicketResaleListingDomainObjectAbstract::ID => $listingId,
            TicketResaleListingDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($listing === null) {
            throw new ResourceNotFoundException(__('Resale listing not found'));
        }

        $this->describe(collect([$listing]));

        return $listing;
    }

    private function withRelations(): TicketResaleListingRepositoryInterface
    {
        return $this->listingRepository
            ->loadRelation(new Relationship(domainObject: AttendeeDomainObject::class, nested: [
                new Relationship(domainObject: ProductDomainObject::class, nested: [
                    new Relationship(domainObject: ProductPriceDomainObject::class),
                ], name: 'product'),
            ], name: 'attendee'))
            ->loadRelation(new Relationship(domainObject: OrderDomainObject::class, name: 'order'))
            ->loadRelation(new Relationship(domainObject: OrderDomainObject::class, name: 'sold_order'));
    }

    /**
     * @param  Collection<int, TicketResaleListingDomainObject>  $listings
     */
    private function describe(Collection $listings): void
    {
        $listed = $listings->filter(
            fn (TicketResaleListingDomainObject $listing) => $listing->getStatus() === TicketResaleListingStatus::LISTED->name
        );

        $liveClaimExpiries = $this->listingRepository->findLiveClaimExpiries(
            $listed->map(fn (TicketResaleListingDomainObject $listing) => $listing->getId())->values()->all()
        );

        $effectiveListingIds = $this->listingRepository
            ->findEffectiveForAttendees(
                $listed->map(fn (TicketResaleListingDomainObject $listing) => $listing->getAttendeeId())->values()->all()
            )
            ->map(fn (TicketResaleListingDomainObject $listing) => $listing->getId());

        $listings->each(function (TicketResaleListingDomainObject $listing) use ($liveClaimExpiries, $effectiveListingIds): void {
            $heldUntil = $liveClaimExpiries[$listing->getId()] ?? null;

            $listing
                ->setHeldUntil($heldUntil === null ? null : Carbon::parse($heldUntil, 'UTC')->toIso8601String())
                ->setState(match (true) {
                    $listing->getStatus() === TicketResaleListingStatus::SOLD->name => TicketResaleListingState::SOLD,
                    $listing->getStatus() === TicketResaleListingStatus::CLOSED->name => TicketResaleListingState::CLOSED,
                    $heldUntil !== null && $listing->getWithdrawalRequestedAt() !== null => TicketResaleListingState::WITHDRAWAL_REQUESTED,
                    $heldUntil !== null => TicketResaleListingState::HELD,
                    $effectiveListingIds->contains($listing->getId()) => TicketResaleListingState::OPEN,
                    default => TicketResaleListingState::NOT_OFFERED,
                });
        });
    }
}

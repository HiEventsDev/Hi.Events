<?php

namespace HiEvents\Services\Domain\TicketResale;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\Enums\OrderAuditAction;
use HiEvents\DomainObjects\Enums\TicketResaleCloseReason;
use HiEvents\DomainObjects\Enums\TicketResaleNotice;
use HiEvents\DomainObjects\Generated\TicketResaleListingDomainObjectAbstract;
use HiEvents\DomainObjects\Status\TicketResaleListingStatus;
use HiEvents\DomainObjects\TicketResaleListingDomainObject;
use HiEvents\Exceptions\ResourceConflictException;
use HiEvents\Repository\Interfaces\AttendeeRepositoryInterface;
use HiEvents\Repository\Interfaces\TicketResaleListingRepositoryInterface;
use HiEvents\Services\Domain\SelfService\OrderAuditLogService;
use Illuminate\Support\Collection;

class TicketResaleClosureService
{
    private const SELLER_NOTICES = [
        TicketResaleCloseReason::CUTOFF_PASSED->value => TicketResaleNotice::UNSOLD_FOR_SELLER,
        TicketResaleCloseReason::RESALE_TURNED_OFF->value => TicketResaleNotice::UNSOLD_FOR_SELLER,
        TicketResaleCloseReason::TICKET_CANCELLED->value => TicketResaleNotice::CLOSED_FOR_SELLER,
        TicketResaleCloseReason::TICKET_TYPE_CHANGED->value => TicketResaleNotice::CLOSED_FOR_SELLER,
        TicketResaleCloseReason::DATE_CANCELLED->value => TicketResaleNotice::CLOSED_FOR_SELLER,
        TicketResaleCloseReason::CLOSED_BY_ORGANIZER->value => TicketResaleNotice::CLOSED_FOR_SELLER,
    ];

    private const HOLDER_NOTICES = [
        TicketResaleCloseReason::WITHDRAWN->value => TicketResaleNotice::RETURNED_FOR_HOLDER,
        TicketResaleCloseReason::CUTOFF_PASSED->value => TicketResaleNotice::RETURNED_FOR_HOLDER,
        TicketResaleCloseReason::RESALE_TURNED_OFF->value => TicketResaleNotice::RETURNED_FOR_HOLDER,
        TicketResaleCloseReason::TICKET_TYPE_CHANGED->value => TicketResaleNotice::RETURNED_FOR_HOLDER,
        TicketResaleCloseReason::CLOSED_BY_ORGANIZER->value => TicketResaleNotice::RETURNED_FOR_HOLDER,
    ];

    public function __construct(
        private readonly TicketResaleListingRepositoryInterface $listingRepository,
        private readonly OrderAuditLogService $orderAuditLogService,
        private readonly TicketResaleNotificationService $notificationService,
        private readonly AttendeeRepositoryInterface $attendeeRepository,
    ) {}

    /**
     * @param  int[]  $attendeeIds
     */
    public function closeForAttendees(array $attendeeIds, TicketResaleCloseReason $reason): void
    {
        if ($attendeeIds === []) {
            return;
        }

        $this->listingRepository
            ->findWhereIn(TicketResaleListingDomainObjectAbstract::ATTENDEE_ID, $attendeeIds, [
                TicketResaleListingDomainObjectAbstract::STATUS => TicketResaleListingStatus::LISTED->name,
            ])
            ->each(fn (TicketResaleListingDomainObject $listing) => $this->close($listing, $reason));
    }

    public function closeForOrder(int $orderId, TicketResaleCloseReason $reason): void
    {
        $this->listingRepository
            ->findWhere([
                TicketResaleListingDomainObjectAbstract::ORDER_ID => $orderId,
                TicketResaleListingDomainObjectAbstract::STATUS => TicketResaleListingStatus::LISTED->name,
            ])
            ->each(fn (TicketResaleListingDomainObject $listing) => $this->close($listing, $reason));
    }

    /**
     * @throws ResourceConflictException
     */
    public function closeUnheldForAttendee(int $attendeeId, TicketResaleCloseReason $reason): void
    {
        $listing = $this->listingRepository->findListedForAttendeeLocked($attendeeId);

        if ($listing === null) {
            return;
        }

        $this->assertNotHeld($listing);
        $this->close($listing, $reason);
    }

    public function lockForCheckIn(int $attendeeId): AttendeeDomainObject
    {
        $this->listingRepository->findListedForAttendeeLocked($attendeeId);

        return $this->attendeeRepository->findByIdLocked($attendeeId);
    }

    /**
     * @throws ResourceConflictException
     */
    public function releaseForCheckIn(int $attendeeId): void
    {
        $listing = $this->listingRepository->findListedForAttendeeLocked($attendeeId);

        if ($listing === null) {
            return;
        }

        if ($this->listingRepository->findEffectiveForAttendee($attendeeId) !== null) {
            throw new ResourceConflictException(__('This ticket is listed for resale and cannot be checked in.'));
        }

        $this->close($listing, TicketResaleCloseReason::TICKET_USED);
    }

    /**
     * @throws ResourceConflictException
     */
    public function closeUnheld(TicketResaleListingDomainObject $listing, TicketResaleCloseReason $reason): void
    {
        $this->assertNotHeld($listing);
        $this->close($listing, $reason);
    }

    public function closeLapsed(int $limit): int
    {
        return $this->closeEach($this->listingRepository->findUnheldWithWithdrawalRequest($limit), TicketResaleCloseReason::WITHDRAWN)
            + $this->closeEach($this->listingRepository->findUnheldPastCutoff($limit), TicketResaleCloseReason::CUTOFF_PASSED)
            + $this->closeEach($this->listingRepository->findUnheldNoLongerOffered($limit), TicketResaleCloseReason::RESALE_TURNED_OFF);
    }

    /**
     * @param  Collection<int, TicketResaleListingDomainObject>  $listings
     */
    private function closeEach(Collection $listings, TicketResaleCloseReason $reason): int
    {
        return $listings
            ->filter(fn (TicketResaleListingDomainObject $listing) => $this->close($listing, $reason))
            ->count();
    }

    /**
     * @throws ResourceConflictException
     */
    private function assertNotHeld(TicketResaleListingDomainObject $listing): void
    {
        if ($this->listingRepository->hasLiveClaim($listing->getId())) {
            throw new ResourceConflictException(
                __('A buyer is checking out with this ticket right now. Try again in a few minutes.')
            );
        }
    }

    private function close(TicketResaleListingDomainObject $listing, TicketResaleCloseReason $reason): bool
    {
        if (! $this->listingRepository->closeIfListed($listing->getId(), $reason->value)) {
            return false;
        }

        $closedListing = $this->listingRepository->findById($listing->getId());

        $this->orderAuditLogService->logTicketResale(OrderAuditAction::TICKET_RESALE_CLOSED, $closedListing);

        if (isset(self::SELLER_NOTICES[$reason->value])) {
            $this->notificationService->notifySeller(self::SELLER_NOTICES[$reason->value], $closedListing);
        }

        if (isset(self::HOLDER_NOTICES[$reason->value])) {
            $this->notificationService->notifyHolder(self::HOLDER_NOTICES[$reason->value], $closedListing);
        }

        return true;
    }
}

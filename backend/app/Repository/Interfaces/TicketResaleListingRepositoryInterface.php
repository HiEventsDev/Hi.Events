<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\TicketResaleListingDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<TicketResaleListingDomainObject>
 */
interface TicketResaleListingRepositoryInterface extends RepositoryInterface
{
    public function findByIdLocked(int $listingId): ?TicketResaleListingDomainObject;

    public function findListedForAttendeeLocked(int $attendeeId): ?TicketResaleListingDomainObject;

    public function hasLiveClaim(int $listingId): bool;

    public function findEffectiveForAttendee(int $attendeeId): ?TicketResaleListingDomainObject;

    /**
     * @param  int[]  $attendeeIds
     * @return Collection<int, TicketResaleListingDomainObject> effective listings keyed by attendee id
     */
    public function findEffectiveForAttendees(array $attendeeIds): Collection;

    /**
     * @param  int[]  $eventIds
     * @return array<int, int> product_price_id => listings a new order could take
     */
    public function countAvailableByPrice(array $eventIds, ?int $eventOccurrenceId = null): array;

    /**
     * @param  int[]  $eventIds
     * @return array<int, int> product_price_id => listings held by unexpired reservations
     */
    public function countLiveClaimsByPrice(array $eventIds, ?int $eventOccurrenceId = null): array;

    public function countLiveClaimsForOccurrence(int $eventOccurrenceId): int;

    /**
     * @return int[] ids of the event's dates that have at least one listed ticket a new order could take
     */
    public function occurrenceIdsWithAvailableListings(int $eventId): array;

    /**
     * @return Collection<int, TicketResaleListingDomainObject> oldest first, locked for update
     */
    public function findClaimableLocked(
        int $productPriceId,
        int $eventOccurrenceId,
        float $unitPrice,
        int $limit,
    ): Collection;

    /**
     * @return Collection<int, TicketResaleListingDomainObject>
     */
    public function findListedClaimedByOrderLocked(int $orderId): Collection;

    /**
     * @return Collection<int, TicketResaleListingDomainObject> unheld listings whose seller asked to withdraw
     */
    public function findUnheldWithWithdrawalRequest(int $limit): Collection;

    /**
     * @return Collection<int, TicketResaleListingDomainObject> unheld listings past the resale cutoff
     */
    public function findUnheldPastCutoff(int $limit): Collection;

    /**
     * @return Collection<int, TicketResaleListingDomainObject> unheld listings on events that no longer offer resale
     */
    public function findUnheldNoLongerOffered(int $limit): Collection;

    public function closeIfListed(int $listingId, string $closedReason): bool;

    /**
     * @return LengthAwarePaginator<int, TicketResaleListingDomainObject> newest first
     */
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;

    /**
     * @param  int[]  $listingIds
     * @return array<int, string> listing id => when the reservation holding it ends
     */
    public function findLiveClaimExpiries(array $listingIds): array;

    public function countUnsellableClaimsForOrder(int $orderId): int;

    /**
     * @return int[] ids of sold listings whose refund has not started and that were last touched before the given time
     */
    public function touchRefundsNotStartedSince(string $untouchedSince, int $limit): array;

    /**
     * @return int[] ids of sold listings whose Stripe refund was created but not settled and that were last touched before the given time
     */
    public function touchRefundsAwaitingStripeSince(string $untouchedSince, int $limit): array;
}

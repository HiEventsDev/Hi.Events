<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\Enums\TicketResaleCloseReason;
use HiEvents\DomainObjects\Generated\TicketResaleListingDomainObjectAbstract;
use HiEvents\DomainObjects\Status\EventOccurrenceStatus;
use HiEvents\DomainObjects\Status\EventStatus;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\TicketResaleListingStatus;
use HiEvents\DomainObjects\Status\TicketResaleRefundStatus;
use HiEvents\DomainObjects\TicketResaleListingDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\TicketResaleListing;
use HiEvents\Repository\Interfaces\TicketResaleListingRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<TicketResaleListingDomainObject>
 */
class TicketResaleListingRepository extends BaseRepository implements TicketResaleListingRepositoryInterface
{
    private const LIVE_CLAIM = <<<'SQL'
        EXISTS (
            SELECT 1 FROM orders AS claiming_orders
            WHERE claiming_orders.id = ticket_resale_listings.claimed_by_order_id
              AND claiming_orders.deleted_at IS NULL
              AND claiming_orders.status = ?
              AND claiming_orders.reserved_until > NOW()
        )
    SQL;

    private const BEFORE_CUTOFF = <<<'SQL'
        EXISTS (
            SELECT 1 FROM event_occurrences
            JOIN event_settings ON event_settings.event_id = event_occurrences.event_id
            WHERE event_occurrences.id = ticket_resale_listings.event_occurrence_id
              AND event_occurrences.deleted_at IS NULL
              AND event_occurrences.status <> ?
              AND event_occurrences.start_date - make_interval(mins => event_settings.ticket_resale_cutoff_minutes) > NOW()
        )
    SQL;

    private const OPEN_TO_NEW_BUYERS = <<<'SQL'
        ticket_resale_listings.withdrawal_requested_at IS NULL
        AND EXISTS (
            SELECT 1 FROM events
            JOIN event_settings ON event_settings.event_id = events.id
            WHERE events.id = ticket_resale_listings.event_id
              AND events.deleted_at IS NULL
              AND events.status = ?
              AND event_settings.allow_ticket_resale = TRUE
        )
        AND EXISTS (
            SELECT 1 FROM product_prices
            JOIN products ON products.id = product_prices.product_id
            WHERE product_prices.id = ticket_resale_listings.product_price_id
              AND products.is_resellable = TRUE
        )
    SQL;

    private const CURRENT_PRICE_COVERS_MINIMUM = <<<'SQL'
        EXISTS (
            SELECT 1 FROM product_prices
            LEFT JOIN product_price_occurrence_overrides AS overrides
                ON overrides.product_price_id = product_prices.id
               AND overrides.event_occurrence_id = ticket_resale_listings.event_occurrence_id
            WHERE product_prices.id = ticket_resale_listings.product_price_id
              AND product_prices.deleted_at IS NULL
              AND COALESCE(overrides.price, product_prices.price) >= ticket_resale_listings.min_unit_price
        )
    SQL;

    private const PAID_ON_CURRENT_STRIPE_ACCOUNT = <<<'SQL'
        ticket_resale_listings.connected_account_id IS NOT DISTINCT FROM (
            SELECT organizer_stripe_platforms.stripe_account_id
            FROM organizer_stripe_platforms
            JOIN events ON events.organizer_id = organizer_stripe_platforms.organizer_id
            WHERE events.id = ticket_resale_listings.event_id
              AND organizer_stripe_platforms.deleted_at IS NULL
              AND organizer_stripe_platforms.stripe_setup_completed_at IS NOT NULL
            ORDER BY organizer_stripe_platforms.created_at DESC, organizer_stripe_platforms.id DESC
            LIMIT 1
        )
    SQL;

    protected function getModel(): string
    {
        return TicketResaleListing::class;
    }

    public function getDomainObject(): string
    {
        return TicketResaleListingDomainObject::class;
    }

    public function findByIdLocked(int $listingId): ?TicketResaleListingDomainObject
    {
        return $this->runQuery(function () use ($listingId) {
            $model = TicketResaleListing::query()->whereKey($listingId)->lockForUpdate()->first();

            return $model === null ? null : $this->handleSingleResult($model);
        });
    }

    public function findListedForAttendeeLocked(int $attendeeId): ?TicketResaleListingDomainObject
    {
        return $this->runQuery(function () use ($attendeeId) {
            $model = $this->listed()->where('attendee_id', $attendeeId)->lockForUpdate()->first();

            return $model === null ? null : $this->handleSingleResult($model);
        });
    }

    public function hasLiveClaim(int $listingId): bool
    {
        return $this->runQuery(fn () => $this->liveClaimed($this->listed())->whereKey($listingId)->exists());
    }

    public function findEffectiveForAttendee(int $attendeeId): ?TicketResaleListingDomainObject
    {
        return $this->findEffectiveForAttendees([$attendeeId])->first();
    }

    public function findEffectiveForAttendees(array $attendeeIds): Collection
    {
        if ($attendeeIds === []) {
            return collect();
        }

        return $this->runQuery(fn () => $this->handleResults(
            $this->effective()->whereIn('attendee_id', $attendeeIds)->get()
        )->keyBy(fn (TicketResaleListingDomainObject $listing) => $listing->getAttendeeId()));
    }

    public function countAvailableByPrice(array $eventIds, ?int $eventOccurrenceId = null): array
    {
        if ($eventIds === []) {
            return [];
        }

        return $this->runQuery(fn () => $this->countByPrice(
            $this->available()->whereRaw(self::CURRENT_PRICE_COVERS_MINIMUM),
            $eventIds,
            $eventOccurrenceId,
        ));
    }

    public function countLiveClaimsByPrice(array $eventIds, ?int $eventOccurrenceId = null): array
    {
        if ($eventIds === []) {
            return [];
        }

        return $this->runQuery(fn () => $this->countByPrice($this->liveClaimed($this->listed()), $eventIds, $eventOccurrenceId));
    }

    public function countLiveClaimsForOccurrence(int $eventOccurrenceId): int
    {
        return $this->runQuery(fn () => $this->liveClaimed($this->listed())
            ->where('event_occurrence_id', $eventOccurrenceId)
            ->count());
    }

    public function occurrenceIdsWithAvailableListings(int $eventId): array
    {
        return $this->runQuery(fn () => $this->available()
            ->whereRaw(self::CURRENT_PRICE_COVERS_MINIMUM)
            ->where('ticket_resale_listings.event_id', $eventId)
            ->distinct()
            ->pluck('ticket_resale_listings.event_occurrence_id')
            ->map(fn ($occurrenceId) => (int) $occurrenceId)
            ->all());
    }

    public function findClaimableLocked(
        int $productPriceId,
        int $eventOccurrenceId,
        float $unitPrice,
        int $limit,
    ): Collection {
        return $this->runQuery(fn () => $this->handleResults(
            $this->available()
                ->where('product_price_id', $productPriceId)
                ->where('event_occurrence_id', $eventOccurrenceId)
                ->where('min_unit_price', '<=', $unitPrice)
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get()
        ));
    }

    public function findListedClaimedByOrderLocked(int $orderId): Collection
    {
        return $this->runQuery(fn () => $this->handleResults(
            $this->listed()->where('claimed_by_order_id', $orderId)->orderBy('order_id')->orderBy('id')->lockForUpdate()->get()
        ));
    }

    public function countUnsellableClaimsForOrder(int $orderId): int
    {
        return $this->runQuery(fn () => TicketResaleListing::query()
            ->where('claimed_by_order_id', $orderId)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $listed) => $listed
                    ->where('status', TicketResaleListingStatus::LISTED->name)
                    ->whereRaw('NOT '.self::BEFORE_CUTOFF, [EventOccurrenceStatus::CANCELLED->name]))
                ->orWhere(fn (Builder $closed) => $closed
                    ->where('status', TicketResaleListingStatus::CLOSED->name)
                    ->whereNotIn('closed_reason', [
                        TicketResaleCloseReason::TICKET_CANCELLED->value,
                        TicketResaleCloseReason::TICKET_TYPE_CHANGED->value,
                        TicketResaleCloseReason::DATE_CANCELLED->value,
                    ])))
            ->count());
    }

    public function findUnheldWithWithdrawalRequest(int $limit): Collection
    {
        return $this->runQuery(fn () => $this->handleResults(
            $this->unheld()->whereNotNull('withdrawal_requested_at')->orderBy('id')->limit($limit)->get()
        ));
    }

    public function findUnheldPastCutoff(int $limit): Collection
    {
        return $this->runQuery(fn () => $this->handleResults(
            $this->unheld()
                ->whereNull('withdrawal_requested_at')
                ->whereRaw('NOT '.self::BEFORE_CUTOFF, [EventOccurrenceStatus::CANCELLED->name])
                ->orderBy('id')
                ->limit($limit)
                ->get()
        ));
    }

    public function findUnheldNoLongerOffered(int $limit): Collection
    {
        return $this->runQuery(fn () => $this->handleResults(
            $this->unheld()
                ->whereNull('withdrawal_requested_at')
                ->whereRaw(self::BEFORE_CUTOFF, [EventOccurrenceStatus::CANCELLED->name])
                ->whereRaw('NOT ('.self::OPEN_TO_NEW_BUYERS.')', [EventStatus::LIVE->name])
                ->orderBy('id')
                ->limit($limit)
                ->get()
        ));
    }

    public function closeIfListed(int $listingId, string $closedReason): bool
    {
        return $this->runQuery(fn () => $this->listed()->whereKey($listingId)->update([
            'status' => TicketResaleListingStatus::CLOSED->name,
            'closed_reason' => $closedReason,
        ]) === 1);
    }

    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator
    {
        return $this->runQuery(function () use ($eventId, $params) {
            $this->applyFilterFields($params, TicketResaleListingDomainObject::getAllowedFilterFields());

            $this->model = $this->model
                ->orderBy(TicketResaleListingDomainObjectAbstract::CREATED_AT, 'desc')
                ->orderBy(TicketResaleListingDomainObjectAbstract::ID, 'desc');

            return $this->paginateWhere(
                where: [TicketResaleListingDomainObjectAbstract::EVENT_ID => $eventId],
                limit: $params->per_page,
                page: $params->page,
            );
        });
    }

    public function findLiveClaimExpiries(array $listingIds): array
    {
        if ($listingIds === []) {
            return [];
        }

        return $this->runQuery(fn () => $this->liveClaimed($this->listed())
            ->whereIn('ticket_resale_listings.id', $listingIds)
            ->join('orders', 'orders.id', '=', 'ticket_resale_listings.claimed_by_order_id')
            ->pluck('orders.reserved_until', 'ticket_resale_listings.id')
            ->all());
    }

    public function touchRefundsNotStartedSince(string $untouchedSince, int $limit): array
    {
        return $this->touchPendingRefunds(fn (Builder $query) => $query->whereNull('stripe_refund_id'), $untouchedSince, $limit);
    }

    public function touchRefundsAwaitingStripeSince(string $untouchedSince, int $limit): array
    {
        return $this->touchPendingRefunds(fn (Builder $query) => $query->whereNotNull('stripe_refund_id'), $untouchedSince, $limit);
    }

    private function touchPendingRefunds(callable $scope, string $untouchedSince, int $limit): array
    {
        return $this->runQuery(function () use ($scope, $untouchedSince, $limit) {
            $listingIds = TicketResaleListing::query()
                ->where('status', TicketResaleListingStatus::SOLD->name)
                ->where('refund_status', TicketResaleRefundStatus::PENDING->name)
                ->tap($scope)
                ->where('updated_at', '<', $untouchedSince)
                ->orderBy('id')
                ->limit($limit)
                ->pluck('id')
                ->all();

            TicketResaleListing::query()->whereKey($listingIds)->touch();

            return $listingIds;
        });
    }

    private function listed(): Builder
    {
        return TicketResaleListing::query()->where('ticket_resale_listings.status', TicketResaleListingStatus::LISTED->name);
    }

    private function unheld(): Builder
    {
        return $this->listed()->whereRaw('NOT '.self::LIVE_CLAIM, [OrderStatus::RESERVED->name]);
    }

    private function liveClaimed(Builder $query): Builder
    {
        return $query->whereRaw(self::LIVE_CLAIM, [OrderStatus::RESERVED->name]);
    }

    private function beforeCutoff(): Builder
    {
        return $this->listed()->whereRaw(self::BEFORE_CUTOFF, [EventOccurrenceStatus::CANCELLED->name]);
    }

    private function effective(): Builder
    {
        return $this->beforeCutoff()->where(fn (Builder $query) => $query
            ->whereRaw(self::LIVE_CLAIM, [OrderStatus::RESERVED->name])
            ->orWhereRaw(self::OPEN_TO_NEW_BUYERS, [EventStatus::LIVE->name]));
    }

    private function available(): Builder
    {
        return $this->beforeCutoff()
            ->whereRaw('NOT '.self::LIVE_CLAIM, [OrderStatus::RESERVED->name])
            ->whereRaw(self::OPEN_TO_NEW_BUYERS, [EventStatus::LIVE->name])
            ->whereRaw(self::PAID_ON_CURRENT_STRIPE_ACCOUNT)
            ->where('ticket_resale_listings.min_unit_price', '>', 0);
    }

    /**
     * @param  int[]  $eventIds
     * @return array<int, int>
     */
    private function countByPrice(Builder $query, array $eventIds, ?int $eventOccurrenceId): array
    {
        return $query
            ->whereIn('ticket_resale_listings.event_id', $eventIds)
            ->when($eventOccurrenceId !== null, fn (Builder $scoped) => $scoped->where('ticket_resale_listings.event_occurrence_id', $eventOccurrenceId))
            ->groupBy('ticket_resale_listings.product_price_id')
            ->selectRaw('ticket_resale_listings.product_price_id, COUNT(*) AS quantity')
            ->pluck('quantity', 'product_price_id')
            ->map(fn ($quantity) => (int) $quantity)
            ->all();
    }
}

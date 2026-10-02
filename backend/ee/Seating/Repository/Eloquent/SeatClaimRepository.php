<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Repository\Eloquent;

use Closure;
use HiEvents\DomainObjects\SeatClaimDomainObject;
use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\DomainObjects\Status\SeatClaimStatus;
use HiEvents\Enterprise\Seating\Repository\Interfaces\SeatClaimRepositoryInterface;
use HiEvents\Enterprise\Seating\Services\Domain\SeatClaimLiveness;
use HiEvents\Models\SeatClaim;
use HiEvents\Repository\Eloquent\BaseRepository;
use HiEvents\Repository\Interfaces\EventOccurrenceRepositoryInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Application;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<SeatClaimDomainObject>
 */
class SeatClaimRepository extends BaseRepository implements SeatClaimRepositoryInterface
{
    private const LONG_DEAD_AFTER_HOURS = 24;

    private const ENDED_OCCURRENCE_GRACE_HOURS = 24;

    private const RESERVATION_EXPIRY = "COALESCE(orders.reserved_until, '-infinity')";

    public function __construct(
        Application $application,
        DatabaseManager $db,
        private readonly EventOccurrenceRepositoryInterface $eventOccurrenceRepository,
    ) {
        parent::__construct($application, $db);
    }

    protected function getModel(): string
    {
        return SeatClaim::class;
    }

    public function getDomainObject(): string
    {
        return SeatClaimDomainObject::class;
    }

    public function deleteDeadClaimsForSeats(array $occurrenceIds, array $seatUids): void
    {
        $this->deleteDeadClaims(fn (Builder $query) => $query
            ->whereIn('seat_claims.event_occurrence_id', $occurrenceIds)
            ->where('seat_claims.is_zone', false)
            ->whereIn('seat_claims.seat_uid', $seatUids));
    }

    public function deleteLongDeadClaims(int $occurrenceId): void
    {
        $this->deleteDeadClaims(fn (Builder $query) => $query
            ->where('seat_claims.event_occurrence_id', $occurrenceId)
            ->whereRaw(self::RESERVATION_EXPIRY.' < ?', [now()->subHours(self::LONG_DEAD_AFTER_HOURS)]));
    }

    public function deleteDeadClaimsForRemovedSeats(int $eventId, array $keptSeatUids): void
    {
        $this->deleteDeadClaims(fn (Builder $query) => $query
            ->where('seat_claims.event_id', $eventId)
            ->whereNotIn('seat_claims.seat_uid', $keptSeatUids));
    }

    public function insertIgnoringConflicts(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        return $this->runQuery(function () use ($rows) {
            $columns = array_keys($rows[0]);
            $placeholders = implode(', ', array_fill(0, count($rows), '('.implode(', ', array_fill(0, count($columns), '?')).')'));

            $inserted = DB::select(
                'INSERT INTO seat_claims ('.implode(', ', $columns).') VALUES '.$placeholders
                .' ON CONFLICT DO NOTHING RETURNING seat_uid, is_zone',
                array_merge(...array_map('array_values', $rows)),
            );

            return array_values(array_map(
                static fn (object $row) => $row->seat_uid,
                array_filter($inserted, static fn (object $row) => ! $row->is_zone),
            ));
        });
    }

    public function insertBlocks(int $eventId, array $occurrenceIds, array $seats, ?string $reason): Collection
    {
        if ($occurrenceIds === [] || $seats === []) {
            return collect();
        }

        return $this->runQuery(fn () => collect(DB::select(
            'INSERT INTO seat_claims (event_id, event_occurrence_id, seat_uid, is_zone, band_key, seat_label, held_back, block_reason, created_at, updated_at)
             SELECT ?, occurrence.id, seat.seat_uid, false, seat.band_key, seat.seat_label, true, ?, ?, ?
             FROM unnest(?::bigint[]) AS occurrence(id)
             CROSS JOIN jsonb_to_recordset(?::jsonb) AS seat(seat_uid varchar, band_key varchar, seat_label varchar)
             ON CONFLICT DO NOTHING
             RETURNING event_occurrence_id, seat_uid',
            [
                $eventId,
                $reason,
                now(),
                now(),
                $this->bigintArray($occurrenceIds),
                json_encode(array_values($seats), JSON_THROW_ON_ERROR),
            ],
        )));
    }

    public function countLiveForZone(int $occurrenceId, string $zoneUid, ?int $excludingOrderId = null): int
    {
        return $this->runQuery(fn () => $this->liveClaims()
            ->where('seat_claims.event_occurrence_id', $occurrenceId)
            ->where('seat_claims.is_zone', true)
            ->where('seat_claims.seat_uid', $zoneUid)
            ->when($excludingOrderId !== null, fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->whereNull('seat_claims.order_id')
                ->orWhere('seat_claims.order_id', '!=', $excludingOrderId)))
            ->count());
    }

    public function findLiveForOccurrence(int $occurrenceId): Collection
    {
        return $this->runQuery(fn () => $this->liveClaims()
            ->where('seat_claims.event_occurrence_id', $occurrenceId)
            ->get(['seat_claims.seat_uid', 'seat_claims.is_zone', 'seat_claims.band_key']));
    }

    public function findTakenSeatUids(int $occurrenceId, array $except = []): array
    {
        return $this->runQuery(fn () => $this->liveClaims()
            ->where('seat_claims.event_occurrence_id', $occurrenceId)
            ->where('seat_claims.is_zone', false)
            ->when($except !== [], fn (Builder $query) => $query->whereNotIn('seat_claims.seat_uid', $except))
            ->pluck('seat_claims.seat_uid')
            ->all());
    }

    public function findLiveWithHoldersForOccurrence(int $occurrenceId): Collection
    {
        return $this->runQuery(fn () => $this->liveClaims()
            ->leftJoin('attendees', 'attendees.id', '=', 'seat_claims.attendee_id')
            ->where('seat_claims.event_occurrence_id', $occurrenceId)
            ->orderBy('seat_claims.id')
            ->select([
                'seat_claims.seat_uid',
                'seat_claims.seat_label',
                'seat_claims.is_zone',
                'seat_claims.band_key',
                'seat_claims.block_reason',
                'attendees.public_id AS attendee_public_id',
                'attendees.first_name AS attendee_first_name',
                'attendees.last_name AS attendee_last_name',
            ])
            ->selectRaw($this->statusExpression().' AS status', [now()])
            ->get());
    }

    public function deleteBlocks(array $occurrenceIds, array $seatUids): Collection
    {
        if ($occurrenceIds === [] || $seatUids === []) {
            return collect();
        }

        return $this->runQuery(fn () => collect(DB::select(
            'DELETE FROM seat_claims
             WHERE event_occurrence_id = ANY(?::bigint[])
               AND seat_uid IN (SELECT jsonb_array_elements_text(?::jsonb))
               AND is_zone = false
               AND '.$this->blockCondition().'
             RETURNING event_occurrence_id, seat_uid, block_reason',
            [$this->bigintArray($occurrenceIds), json_encode(array_values($seatUids), JSON_THROW_ON_ERROR), now()],
        )));
    }

    public function releaseBlocks(array $occurrenceIds, array $seatUids): int
    {
        if ($occurrenceIds === [] || $seatUids === []) {
            return 0;
        }

        $deleted = $this->deleteBlocks($occurrenceIds, $seatUids)->count();

        return $deleted + $this->runQuery(fn () => DB::update(
            'UPDATE seat_claims SET held_back = false, block_reason = NULL, updated_at = ?
             WHERE event_occurrence_id = ANY(?::bigint[])
               AND seat_uid IN (SELECT jsonb_array_elements_text(?::jsonb))
               AND is_zone = false
               AND held_back
               AND NOT '.$this->blockCondition(),
            [now(), $this->bigintArray($occurrenceIds), json_encode(array_values($seatUids), JSON_THROW_ON_ERROR), now()],
        ));
    }

    public function releaseForAttendees(array $attendeeIds): void
    {
        if ($attendeeIds === []) {
            return;
        }

        $this->runQuery(function () use ($attendeeIds) {
            DB::table('seat_claims')
                ->whereIn('attendee_id', $attendeeIds)
                ->where('held_back', true)
                ->update([
                    'order_id' => null,
                    'order_item_id' => null,
                    'attendee_id' => null,
                    'product_id' => null,
                    'product_price_id' => null,
                    'updated_at' => now(),
                ]);

            DB::table('seat_claims')->whereIn('attendee_id', $attendeeIds)->delete();
        });
    }

    public function findLiveSeatsForEvent(int $eventId): Collection
    {
        return $this->runQuery(fn () => $this->liveClaimsOnUpcomingDates($eventId)
            ->distinct()
            ->get(['seat_claims.seat_uid', 'seat_claims.is_zone', 'seat_claims.band_key', 'seat_claims.seat_label', 'seat_claims.product_id']));
    }

    public function findLiveZoneOccupancyForEvent(int $eventId): array
    {
        return $this->runQuery(fn () => DB::query()
            ->fromSub(
                $this->liveClaimsOnUpcomingDates($eventId)
                    ->where('seat_claims.is_zone', true)
                    ->groupBy('seat_claims.seat_uid', 'seat_claims.event_occurrence_id')
                    ->select('seat_claims.seat_uid')
                    ->selectRaw('COUNT(*) AS claimed'),
                'zone_counts',
            )
            ->groupBy('seat_uid')
            ->selectRaw('seat_uid, MAX(claimed) AS claimed')
            ->pluck('claimed', 'seat_uid')
            ->map(static fn (int|string $claimed) => (int) $claimed)
            ->all());
    }

    public function deleteClaimsWithoutLiveOrderForEvent(int $eventId): void
    {
        $this->runQuery(fn () => DB::delete(
            'DELETE FROM seat_claims
             WHERE event_id = ?
               AND NOT EXISTS (SELECT 1 FROM orders WHERE orders.id = seat_claims.order_id AND '.$this->liveOrderCondition().')',
            [$eventId, now()],
        ));
    }

    public function relabel(int $eventId, array $labelsBySeatUid): void
    {
        if ($labelsBySeatUid === []) {
            return;
        }

        $this->runQuery(function () use ($eventId, $labelsBySeatUid) {
            $incoming = json_encode(
                array_map(
                    static fn (string $seatUid, string $label) => ['seat_uid' => $seatUid, 'seat_label' => $label],
                    array_keys($labelsBySeatUid),
                    $labelsBySeatUid,
                ),
                JSON_THROW_ON_ERROR,
            );
            $scope = [$eventId, $this->bigintArray($this->upcomingOccurrenceIds($eventId))];

            DB::update(
                'UPDATE seat_claims SET seat_label = incoming.seat_label, updated_at = ?
                 FROM jsonb_to_recordset(?::jsonb) AS incoming(seat_uid varchar, seat_label varchar)
                 WHERE seat_claims.seat_uid = incoming.seat_uid
                   AND seat_claims.event_id = ?
                   AND seat_claims.event_occurrence_id = ANY(?::bigint[])',
                [now(), $incoming, ...$scope],
            );

            DB::update(
                'UPDATE attendees SET seat_label = seat_claims.seat_label, updated_at = ?
                 FROM seat_claims, jsonb_to_recordset(?::jsonb) AS incoming(seat_uid varchar, seat_label varchar)
                 WHERE attendees.id = seat_claims.attendee_id
                   AND seat_claims.seat_uid = incoming.seat_uid
                   AND seat_claims.event_id = ?
                   AND seat_claims.event_occurrence_id = ANY(?::bigint[])',
                [now(), $incoming, ...$scope],
            );
        });
    }

    public function liveClaimsWithStatusSql(): string
    {
        return $this->liveClaims()
            ->select([
                'seat_claims.event_id',
                'seat_claims.event_occurrence_id',
                'seat_claims.seat_uid',
                'seat_claims.is_zone',
                'seat_claims.band_key',
            ])
            ->selectRaw($this->statusExpression().' AS status', [now()])
            ->toRawSql();
    }

    private function liveClaims(): Builder
    {
        return DB::table('seat_claims')
            ->leftJoin('orders', 'orders.id', '=', 'seat_claims.order_id')
            ->whereRaw('(seat_claims.order_id IS NULL OR seat_claims.held_back OR '.$this->liveOrderCondition().')', [now()]);
    }

    private function liveClaimsOnUpcomingDates(int $eventId): Builder
    {
        return $this->liveClaims()
            ->where('seat_claims.event_id', $eventId)
            ->whereRaw('seat_claims.event_occurrence_id = ANY(?::bigint[])', [$this->bigintArray($this->upcomingOccurrenceIds($eventId))]);
    }

    /**
     * @return int[]
     */
    private function upcomingOccurrenceIds(int $eventId): array
    {
        return $this->eventOccurrenceRepository->findUpcomingIdsForEvent(
            $eventId,
            now()->subHours(self::ENDED_OCCURRENCE_GRACE_HOURS),
        );
    }

    private function deleteDeadClaims(Closure $scope): void
    {
        $this->runQuery(function () use ($scope) {
            $conditions = DB::table('seat_claims')
                ->where($scope)
                ->where('seat_claims.held_back', false)
                ->where(fn (Builder $dead) => $dead
                    ->whereNotNull('orders.deleted_at')
                    ->orWhereIn('orders.status', $this->names(SeatClaimLiveness::ORDER_STATUSES_DEAD))
                    ->orWhere(fn (Builder $reserved) => $reserved
                        ->whereIn('orders.status', $this->names(SeatClaimLiveness::ORDER_STATUSES_LIVE_UNTIL_EXPIRY))
                        ->whereRaw(self::RESERVATION_EXPIRY.' <= ?', [now()])));

            DB::delete(
                'DELETE FROM seat_claims USING orders WHERE orders.id = seat_claims.order_id AND '
                .preg_replace('/^where /', '', $conditions->getGrammar()->compileWheres($conditions)),
                $conditions->getBindings(),
            );
        });
    }

    private function liveOrderCondition(): string
    {
        return sprintf(
            '(orders.deleted_at IS NULL AND (orders.status IN (%s) OR (orders.status IN (%s) AND %s > ?)))',
            $this->quotedNames(SeatClaimLiveness::ORDER_STATUSES_ALWAYS_LIVE),
            $this->quotedNames(SeatClaimLiveness::ORDER_STATUSES_LIVE_UNTIL_EXPIRY),
            self::RESERVATION_EXPIRY,
        );
    }

    private function blockCondition(): string
    {
        return '(seat_claims.order_id IS NULL OR (seat_claims.held_back AND NOT EXISTS (SELECT 1 FROM orders WHERE orders.id = seat_claims.order_id AND '.$this->liveOrderCondition().')))';
    }

    private function statusExpression(): string
    {
        return sprintf(
            "CASE WHEN COALESCE(%s, false) THEN CASE WHEN orders.status IN (%s) THEN '%s' ELSE '%s' END ELSE '%s' END",
            $this->liveOrderCondition(),
            $this->quotedNames(SeatClaimLiveness::ORDER_STATUSES_LIVE_UNTIL_EXPIRY),
            SeatClaimStatus::HELD->name,
            SeatClaimStatus::SOLD->name,
            SeatClaimStatus::BLOCKED->name,
        );
    }

    /**
     * @param  OrderStatus[]  $statuses
     */
    private function quotedNames(array $statuses): string
    {
        return implode(', ', array_map(static fn (string $name) => "'$name'", $this->names($statuses)));
    }

    /**
     * @param  int[]  $ids
     */
    private function bigintArray(array $ids): string
    {
        return '{'.implode(',', array_map('intval', $ids)).'}';
    }

    /**
     * @param  OrderStatus[]  $statuses
     * @return string[]
     */
    private function names(array $statuses): array
    {
        return array_map(static fn (OrderStatus $status) => $status->name, $statuses);
    }
}

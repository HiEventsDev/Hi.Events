<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Repository\Interfaces;

use HiEvents\DomainObjects\SeatClaimDomainObject;
use HiEvents\Repository\Interfaces\RepositoryInterface;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<SeatClaimDomainObject>
 */
interface SeatClaimRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  int[]  $occurrenceIds
     * @param  string[]  $seatUids
     */
    public function deleteDeadClaimsForSeats(array $occurrenceIds, array $seatUids): void;

    public function deleteLongDeadClaims(int $occurrenceId): void;

    /**
     * @param  string[]  $keptSeatUids
     */
    public function deleteDeadClaimsForRemovedSeats(int $eventId, array $keptSeatUids): void;

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return string[] seat uids of the non-zone rows that were inserted
     */
    public function insertIgnoringConflicts(array $rows): array;

    /**
     * @param  int[]  $occurrenceIds
     * @param  array<int, array{seat_uid: string, band_key: string, seat_label: string}>  $seats
     * @return Collection<int, object{event_occurrence_id: int, seat_uid: string}> the blocks that were inserted
     */
    public function insertBlocks(int $eventId, array $occurrenceIds, array $seats, ?string $reason): Collection;

    public function countLiveForZone(int $occurrenceId, string $zoneUid, ?int $excludingOrderId = null): int;

    /**
     * @return Collection<int, object{seat_uid: string, is_zone: bool, band_key: string}>
     */
    public function findLiveForOccurrence(int $occurrenceId): Collection;

    /**
     * @param  string[]  $except
     * @return string[]
     */
    public function findTakenSeatUids(int $occurrenceId, array $except = []): array;

    /**
     * @return Collection<int, object{seat_uid: string, seat_label: string, is_zone: bool, band_key: string, block_reason: ?string, attendee_public_id: ?string, attendee_first_name: ?string, attendee_last_name: ?string, status: string}>
     */
    public function findLiveWithHoldersForOccurrence(int $occurrenceId): Collection;

    /**
     * @param  int[]  $occurrenceIds
     * @param  string[]  $seatUids
     * @return Collection<int, object{event_occurrence_id: int, seat_uid: string, block_reason: ?string}> the holds that were deleted
     */
    public function deleteBlocks(array $occurrenceIds, array $seatUids): Collection;

    /**
     * @param  int[]  $occurrenceIds
     * @param  string[]  $seatUids
     */
    public function releaseBlocks(array $occurrenceIds, array $seatUids): int;

    /**
     * @param  int[]  $attendeeIds
     */
    public function releaseForAttendees(array $attendeeIds): void;

    /**
     * @return Collection<int, object{seat_uid: string, is_zone: bool, band_key: string, seat_label: string, product_id: ?int}>
     */
    public function findLiveSeatsForEvent(int $eventId): Collection;

    /**
     * @return array<string, int> the most live claims any upcoming date has, keyed by zone uid
     */
    public function findLiveZoneOccupancyForEvent(int $eventId): array;

    public function deleteClaimsWithoutLiveOrderForEvent(int $eventId): void;

    /**
     * @return string SELECT of every live claim with event_id, event_occurrence_id, seat_uid, is_zone, band_key and status (SOLD, HELD or BLOCKED)
     */
    public function liveClaimsWithStatusSql(): string;

    /**
     * @param  array<string, string>  $labelsBySeatUid
     */
    public function relabel(int $eventId, array $labelsBySeatUid): void;
}

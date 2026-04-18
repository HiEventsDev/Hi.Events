<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<AttendeeDomainObject>
 */
interface AttendeeRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;

    public function findByEventIdForExport(int $eventId): Collection;

    public function getAttendeesByCheckInShortId(string $shortId, QueryParamsDTO $params): Paginator;

    /**
     * Bulk-set contact_link_ignored_at on attendees, scoped to the given account (verified via events JOIN).
     *
     * @param  int[]  $attendeeIds
     * @param  ?string  $timestamp  ISO-8601 value to set; null to clear the flag (un-ignore).
     * @return int Number of rows updated.
     */
    public function bulkUpdateContactLinkIgnoredAt(int $accountId, array $attendeeIds, ?string $timestamp): int;
}

<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\DTO\CheckedInAttendeesCountDTO;
use HiEvents\Repository\DTO\CheckInListProductStatDTO;
use HiEvents\Repository\DTO\CheckInListRecentCheckInDTO;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<CheckInListDomainObject>
 */
interface CheckInListRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;

    /**
     * Counts attendees + check-ins for a check-in list. Auto-scopes to the
     * list's own event_occurrence_id when set; callers can override via
     * $eventOccurrenceIdOverride (e.g. when an "All occurrences" list is
     * further narrowed by the client's filter pill).
     */
    public function getCheckedInAttendeeCountById(
        int $checkInListId,
        ?int $eventOccurrenceIdOverride = null,
    ): CheckedInAttendeesCountDTO;

    /**
     * Bulk counterpart used by the admin lists view. Auto-scopes each list
     * to its own event_occurrence_id — no override because each row has
     * independent scope.
     *
     * @param array<int> $checkInListIds
     *
     * @return Collection<CheckedInAttendeesCountDTO>
     */
    public function getCheckedInAttendeeCountByIds(array $checkInListIds): Collection;

    /**
     * @return Collection<int, CheckInListProductStatDTO>
     */
    public function getPerProductCheckInStatsById(
        int $checkInListId,
        ?int $eventOccurrenceIdOverride = null,
    ): Collection;

    /**
     * @return Collection<int, CheckInListRecentCheckInDTO>
     */
    public function getRecentCheckInsById(
        int $checkInListId,
        int $limit,
        ?int $eventOccurrenceIdOverride = null,
    ): Collection;
}

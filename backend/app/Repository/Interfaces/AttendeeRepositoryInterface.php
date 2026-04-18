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

    public function findCheckedInAttendees(int $eventId, ?int $checkInListId = null, array $columns = ['*']): Collection;

    public function findNotCheckedInAttendees(int $eventId, ?int $checkInListId = null, array $columns = ['*']): Collection;

    public function countCheckedInAttendees(int $eventId, ?int $checkInListId = null): int;

    public function countNotCheckedInAttendees(int $eventId, ?int $checkInListId = null): int;
}

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

    public function getCheckedInAttendeeCountById(int $checkInListId): CheckedInAttendeesCountDTO;

    /**
     * @param array<int> $checkInListIds
     *
     * @return Collection<CheckedInAttendeesCountDTO>
     */
    public function getCheckedInAttendeeCountByIds(array $checkInListIds): Collection;

    /**
     * @return Collection<int, CheckInListProductStatDTO>
     */
    public function getPerProductCheckInStatsById(int $checkInListId): Collection;

    /**
     * @return Collection<int, CheckInListRecentCheckInDTO>
     */
    public function getRecentCheckInsById(int $checkInListId, int $limit): Collection;
}

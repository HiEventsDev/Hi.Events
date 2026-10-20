<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Seating\Repository\Interfaces;

use HiEvents\DomainObjects\SeatMapDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\RepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<SeatMapDomainObject>
 */
interface SeatMapRepositoryInterface extends RepositoryInterface
{
    public function findSummariesByOrganizerId(int $organizerId, int $accountId, QueryParamsDTO $params): LengthAwarePaginator;

    public function existsForLiveOrganizer(?int $accountId): bool;
}

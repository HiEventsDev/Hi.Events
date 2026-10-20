<?php

namespace HiEvents\Enterprise\BoxOffice\Repository\Interfaces;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Interfaces\RepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<BoxOfficeDomainObject>
 */
interface BoxOfficeRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;

    public function existsInUseForUpcomingEvent(?int $accountId): bool;

    /**
     * @return string[]
     */
    public function findNamesSellingOnlyProduct(int $productId): array;

    public function detachProduct(int $productId): void;
}

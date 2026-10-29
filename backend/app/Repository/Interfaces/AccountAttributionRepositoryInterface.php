<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\AccountAttributionDomainObject;
use HiEvents\DomainObjects\Enums\AttributionGroupBy;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<AccountAttributionDomainObject>
 */
interface AccountAttributionRepositoryInterface extends RepositoryInterface
{
    public function getAttributionStats(
        AttributionGroupBy $groupBy,
        ?string $dateFrom,
        ?string $dateTo,
        int $perPage,
        int $page
    ): LengthAwarePaginator;

    public function getAttributionSummary(?string $dateFrom, ?string $dateTo): array;
}

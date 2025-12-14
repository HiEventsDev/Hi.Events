<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use Illuminate\Pagination\LengthAwarePaginator;

interface AccountAttributionRepositoryInterface extends RepositoryInterface
{
    public function getAttributionStats(
        string $groupBy,
        ?string $dateFrom,
        ?string $dateTo,
        int $perPage,
        int $page
    ): LengthAwarePaginator;

    public function getAttributionSummary(?string $dateFrom, ?string $dateTo): array;
}

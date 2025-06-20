<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Pagination\LengthAwarePaginator;

interface AffiliateRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;

    public function incrementSales(int $affiliateId, float $amount): void;
}

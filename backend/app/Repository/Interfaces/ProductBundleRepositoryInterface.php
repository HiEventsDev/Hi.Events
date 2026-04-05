<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\ProductBundleDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<ProductBundleDomainObject>
 */
interface ProductBundleRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;
}

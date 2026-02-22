<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<ProductCategoryDomainObject>
 */
interface ProductCategoryRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $queryParamsDTO): Collection;

    public function getNextOrder(int $eventId);
}

<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<ProductCategoryDomainObject>
 */
interface ProductCategoryRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $queryParamsDTO): Collection;

    public function getNextOrder(int $eventId);
}

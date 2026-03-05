<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<PromoCodeDomainObject>
 */
interface PromoCodeRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;
}

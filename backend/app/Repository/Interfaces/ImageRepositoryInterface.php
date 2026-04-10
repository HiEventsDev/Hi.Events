<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\ImageDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<ImageDomainObject>
 */
interface ImageRepositoryInterface extends RepositoryInterface
{
    public function findByAccountId(int $accountId, QueryParamsDTO $params): LengthAwarePaginator;
}

<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\ContactDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<ContactDomainObject>
 */
interface ContactRepositoryInterface extends RepositoryInterface
{
    public function findByAccountId(int $accountId, QueryParamsDTO $params): LengthAwarePaginator;

    public function findByEmailAndAccountId(string $email, int $accountId): ?ContactDomainObject;
}

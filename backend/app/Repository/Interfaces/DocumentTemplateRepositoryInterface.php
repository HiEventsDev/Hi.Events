<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\DocumentTemplateDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<DocumentTemplateDomainObject>
 */
interface DocumentTemplateRepositoryInterface extends RepositoryInterface
{
    public function findByAccountId(int $accountId, QueryParamsDTO $params): LengthAwarePaginator;
}

<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\DocumentTemplateDomainObject;
use HiEvents\DomainObjects\Generated\DocumentTemplateDomainObjectAbstract;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\DocumentTemplate;
use HiEvents\Repository\Interfaces\DocumentTemplateRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepository<DocumentTemplateDomainObject>
 */
class DocumentTemplateRepository extends BaseRepository implements DocumentTemplateRepositoryInterface
{
    protected function getModel(): string
    {
        return DocumentTemplate::class;
    }

    public function getDomainObject(): string
    {
        return DocumentTemplateDomainObject::class;
    }

    public function findByAccountId(int $accountId, QueryParamsDTO $params): LengthAwarePaginator
    {
        return $this->paginateWhere(
            where: [
                [DocumentTemplateDomainObjectAbstract::ACCOUNT_ID, '=', $accountId],
            ],
            limit: $params->per_page,
        );
    }
}

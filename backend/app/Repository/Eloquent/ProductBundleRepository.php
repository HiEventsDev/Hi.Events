<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\ProductBundleDomainObject;
use HiEvents\DomainObjects\Generated\ProductBundleDomainObjectAbstract;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\ProductBundle;
use HiEvents\Repository\Interfaces\ProductBundleRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepository<ProductBundleDomainObject>
 */
class ProductBundleRepository extends BaseRepository implements ProductBundleRepositoryInterface
{
    protected function getModel(): string
    {
        return ProductBundle::class;
    }

    public function getDomainObject(): string
    {
        return ProductBundleDomainObject::class;
    }

    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator
    {
        return $this->paginateWhere(
            where: [
                [ProductBundleDomainObjectAbstract::EVENT_ID, '=', $eventId],
            ],
            limit: $params->per_page,
        );
    }
}

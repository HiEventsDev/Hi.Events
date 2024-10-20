<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\ProductCategoryDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\ProductCategory;
use HiEvents\Repository\Interfaces\ProductCategoryRepositoryInterface;
use Illuminate\Support\Collection;

class ProductCategoryRepository extends BaseRepository implements ProductCategoryRepositoryInterface
{
    protected function getModel(): string
    {
        return ProductCategory::class;
    }

    public function getDomainObject(): string
    {
        return ProductCategoryDomainObject::class;
    }

    public function findByEventId(int $eventId, QueryParamsDTO $queryParamsDTO): Collection
    {
        $query = $this->model
            ->where('event_id', $eventId)
            ->with(['products']);

        // Apply filters from QueryParamsDTO, if needed
        if (!empty($queryParamsDTO->filter_fields)) {
            foreach ($queryParamsDTO->filter_fields as $filter) {
                $query->where($filter->field, $filter->operator ?? '=', $filter->value);
            }
        }

        // Apply sorting from QueryParamsDTO
        if (!empty($queryParamsDTO->sort_by)) {
            $query->orderBy($queryParamsDTO->sort_by, $queryParamsDTO->sort_direction ?? 'asc');
        }

        return $query->get();
    }

    public function getNextOrder(int $eventId)
    {
        return $this->model
            ->where('event_id', $eventId)
            ->max('order') + 1;
    }
}

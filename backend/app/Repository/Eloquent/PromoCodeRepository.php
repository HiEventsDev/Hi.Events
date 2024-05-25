<?php

namespace HiEvents\Repository\Eloquent;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use HiEvents\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\PromoCode;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;

class PromoCodeRepository extends BaseRepository implements PromoCodeRepositoryInterface
{
    protected function getModel(): string
    {
        return PromoCode::class;
    }

    public function getDomainObject(): string
    {
        return PromoCodeDomainObject::class;
    }

    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $where = [
            [PromoCodeDomainObjectAbstract::EVENT_ID, '=', $eventId]
        ];

        if ($params->query) {
            $where[] = static function (Builder $builder) use ($params) {
                $builder
                    ->orWhere(PromoCodeDomainObjectAbstract::CODE, 'ilike', '%' . $params->query . '%');
            };
        }

        $this->model = $this->model->orderBy(
            column: $params->sort_by ?? PromoCodeDomainObject::getDefaultSort(),
            direction: $params->sort_direction ?? 'desc',
        );

        return $this->paginateWhere(
            where: $where,
            limit: $params->per_page,
            page: $params->page,
        );
    }
}

<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\Generated\PromoCodeDomainObjectAbstract;
use HiEvents\DomainObjects\PromoCodeDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\PromoCode;
use HiEvents\Repository\Interfaces\PromoCodeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends BaseRepository<PromoCodeDomainObject>
 */
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
            column: $this->validateSortColumn($params->sort_by, PromoCodeDomainObject::class),
            direction: $this->validateSortDirection($params->sort_direction, PromoCodeDomainObject::class),
        );

        return $this->paginateWhere(
            where: $where,
            limit: $params->per_page,
            page: $params->page,
        );
    }

    public function findByAccountId(int $accountId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $where = [
            [PromoCodeDomainObjectAbstract::ACCOUNT_ID, '=', $accountId],
            static function (Builder $builder) {
                $builder->whereNull(PromoCodeDomainObjectAbstract::EVENT_ID);
            },
        ];

        if ($params->query) {
            $where[] = static function (Builder $builder) use ($params) {
                $builder
                    ->orWhere(PromoCodeDomainObjectAbstract::CODE, 'ilike', '%' . $params->query . '%');
            };
        }

        $this->model = $this->model->orderBy(
            column: $this->validateSortColumn($params->sort_by, PromoCodeDomainObject::class),
            direction: $this->validateSortDirection($params->sort_direction, PromoCodeDomainObject::class),
        );

        return $this->paginateWhere(
            where: $where,
            limit: $params->per_page,
            page: $params->page,
        );
    }

    public function findSiteWideByCode(string $code, int $accountId): ?PromoCodeDomainObject
    {
        return $this->findFirstWhere([
            [PromoCodeDomainObjectAbstract::CODE, '=', $code],
            [PromoCodeDomainObjectAbstract::ACCOUNT_ID, '=', $accountId],
            static function (Builder $builder) {
                $builder->whereNull(PromoCodeDomainObjectAbstract::EVENT_ID);
            },
        ]);
    }
}

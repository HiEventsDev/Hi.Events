<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AffiliateDomainObject;
use HiEvents\DomainObjects\Generated\AffiliateDomainObjectAbstract;
use HiEvents\DomainObjects\Status\AffiliateStatus;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\Affiliate;
use HiEvents\Repository\Interfaces\AffiliateRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class AffiliateRepository extends BaseRepository implements AffiliateRepositoryInterface
{
    protected function getModel(): string
    {
        return Affiliate::class;
    }

    public function getDomainObject(): string
    {
        return AffiliateDomainObject::class;
    }

    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $where = [
            [AffiliateDomainObjectAbstract::EVENT_ID, '=', $eventId]
        ];

        if ($params->query) {
            $where[] = static function (Builder $builder) use ($params) {
                $builder
                    ->orWhere(AffiliateDomainObjectAbstract::NAME, 'ilike', '%' . $params->query . '%')
                    ->orWhere(AffiliateDomainObjectAbstract::CODE, 'ilike', '%' . $params->query . '%')
                    ->orWhere(AffiliateDomainObjectAbstract::EMAIL, 'ilike', '%' . $params->query . '%');
            };
        }

        $this->model = $this->model->orderBy(
            column: $params->sort_by ?? AffiliateDomainObject::getDefaultSort(),
            direction: $params->sort_direction ?? 'desc',
        );

        return $this->paginateWhere(
            where: $where,
            limit: $params->per_page,
            page: $params->page,
        );
    }

    public function findByCodeAndEventId(string $code, int $eventId): ?AffiliateDomainObject
    {
        return $this->findFirstWhere([
            AffiliateDomainObjectAbstract::CODE => $code,
            AffiliateDomainObjectAbstract::EVENT_ID => $eventId,
            AffiliateDomainObjectAbstract::STATUS => AffiliateStatus::ACTIVE->value,
        ]);
    }

    public function incrementSales(int $affiliateId, float $amount): void
    {
        $this->model->where('id', $affiliateId)
            ->increment('total_sales', 1, [
                'total_sales_gross' => $this->db->raw('total_sales_gross + ' . $amount)
            ]);
    }
}

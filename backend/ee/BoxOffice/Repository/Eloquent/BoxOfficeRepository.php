<?php

namespace HiEvents\Enterprise\BoxOffice\Repository\Eloquent;

use HiEvents\DomainObjects\BoxOfficeDomainObject;
use HiEvents\DomainObjects\Generated\BoxOfficeDomainObjectAbstract;
use HiEvents\Enterprise\BoxOffice\Repository\Interfaces\BoxOfficeRepositoryInterface;
use HiEvents\Enterprise\Licensing\Repository\UsageScope;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\BoxOffice;
use HiEvents\Repository\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * @extends BaseRepository<BoxOfficeDomainObject>
 */
class BoxOfficeRepository extends BaseRepository implements BoxOfficeRepositoryInterface
{
    protected function getModel(): string
    {
        return BoxOffice::class;
    }

    public function getDomainObject(): string
    {
        return BoxOfficeDomainObject::class;
    }

    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $where = [
            [BoxOfficeDomainObjectAbstract::EVENT_ID, '=', $eventId],
        ];

        if (! empty($params->query)) {
            $where[] = static function (Builder $builder) use ($params) {
                $builder->where(BoxOfficeDomainObjectAbstract::NAME, 'ilike', '%'.$params->query.'%');
            };
        }

        $this->model = $this->model->orderBy(
            $this->validateSortColumn($params->sort_by, BoxOfficeDomainObject::class),
            $this->validateSortDirection($params->sort_direction, BoxOfficeDomainObject::class),
        );

        return $this->paginateWhere(
            where: $where,
            limit: $params->per_page,
            page: $params->page,
        );
    }

    public function existsInUseForUpcomingEvent(?int $accountId): bool
    {
        return $this->runQuery(fn () => UsageScope::upcomingEvent(
            DB::table('box_offices')->join('events', 'events.id', '=', 'box_offices.event_id'),
            $accountId,
        )
            ->whereNull('box_offices.deleted_at')
            ->where(static function (QueryBuilder $query) {
                $query->where('box_offices.is_system_default', false)
                    ->orWhereNotNull('box_offices.pin_hash')
                    ->orWhereExists(static fn (QueryBuilder $orders) => $orders
                        ->select(DB::raw(1))
                        ->from('orders')
                        ->whereColumn('orders.box_office_id', 'box_offices.id')
                        ->whereNull('orders.deleted_at'));
            })
            ->exists());
    }

    public function findNamesSellingOnlyProduct(int $productId): array
    {
        return $this->runQuery(fn () => DB::table('box_offices')
            ->whereNull('box_offices.deleted_at')
            ->whereExists(static fn (QueryBuilder $assigned) => $assigned
                ->select(DB::raw(1))
                ->from('product_box_offices')
                ->whereColumn('product_box_offices.box_office_id', 'box_offices.id')
                ->where('product_box_offices.product_id', $productId))
            ->whereNotExists(static fn (QueryBuilder $others) => $others
                ->select(DB::raw(1))
                ->from('product_box_offices')
                ->join('products', 'products.id', '=', 'product_box_offices.product_id')
                ->whereColumn('product_box_offices.box_office_id', 'box_offices.id')
                ->where('product_box_offices.product_id', '!=', $productId)
                ->whereNull('products.deleted_at'))
            ->orderBy('box_offices.id')
            ->pluck('box_offices.name')
            ->all());
    }

    public function detachProduct(int $productId): void
    {
        $this->runQuery(fn () => DB::table('product_box_offices')->where('product_id', $productId)->delete());
    }
}

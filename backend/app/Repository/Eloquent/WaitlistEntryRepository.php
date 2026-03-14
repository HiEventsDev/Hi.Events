<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\Generated\OrderDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\ProductPriceDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\WaitlistEntryDomainObjectAbstract;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\ProductDomainObject;
use HiEvents\DomainObjects\ProductPriceDomainObject;
use HiEvents\DomainObjects\Status\WaitlistEntryStatus;
use HiEvents\DomainObjects\WaitlistEntryDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\WaitlistEntry;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\WaitlistEntryRepositoryInterface;
use HiEvents\Services\Application\Handlers\Waitlist\DTO\WaitlistStatsDTO;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class WaitlistEntryRepository extends BaseRepository implements WaitlistEntryRepositoryInterface
{
    protected function getModel(): string
    {
        return WaitlistEntry::class;
    }

    public function getDomainObject(): string
    {
        return WaitlistEntryDomainObject::class;
    }

    public function getStatsByEventId(int $eventId): WaitlistStatsDTO
    {
        $stats = DB::table('waitlist_entries')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as waiting,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as offered,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as purchased,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as expired
            ", [
                WaitlistEntryStatus::WAITING->name,
                WaitlistEntryStatus::OFFERED->name,
                WaitlistEntryStatus::PURCHASED->name,
                WaitlistEntryStatus::CANCELLED->name,
                WaitlistEntryStatus::OFFER_EXPIRED->name,
            ])
            ->where('event_id', $eventId)
            ->whereNull('deleted_at')
            ->first();

        return new WaitlistStatsDTO(
            total: (int) ($stats->total ?? 0),
            waiting: (int) ($stats->waiting ?? 0),
            offered: (int) ($stats->offered ?? 0),
            purchased: (int) ($stats->purchased ?? 0),
            cancelled: (int) ($stats->cancelled ?? 0),
            expired: (int) ($stats->expired ?? 0),
        );
    }

    public function getProductStatsByEventId(int $eventId): \Illuminate\Support\Collection
    {
        return DB::table('waitlist_entries')
            ->join('product_prices', 'waitlist_entries.product_price_id', '=', 'product_prices.id')
            ->join('products', 'product_prices.product_id', '=', 'products.id')
            ->selectRaw("
                waitlist_entries.product_price_id,
                CASE
                    WHEN product_prices.label IS NOT NULL AND product_prices.label != ''
                    THEN products.title || ' - ' || product_prices.label
                    ELSE products.title
                END as product_title,
                SUM(CASE WHEN waitlist_entries.status = ? THEN 1 ELSE 0 END) as waiting,
                SUM(CASE WHEN waitlist_entries.status = ? THEN 1 ELSE 0 END) as offered
            ", [
                WaitlistEntryStatus::WAITING->name,
                WaitlistEntryStatus::OFFERED->name,
            ])
            ->where('waitlist_entries.event_id', $eventId)
            ->whereNull('waitlist_entries.deleted_at')
            ->whereNull('product_prices.deleted_at')
            ->whereNull('products.deleted_at')
            ->groupBy('waitlist_entries.product_price_id', 'products.title', 'product_prices.label')
            ->get();
    }

    public function getMaxPosition(int $productPriceId): int
    {
        return (int) DB::table('waitlist_entries')
            ->where('product_price_id', $productPriceId)
            ->whereNull('deleted_at')
            ->max('position') ?? 0;
    }

    /**
     * @return \Illuminate\Support\Collection<int, WaitlistEntryDomainObject>
     */
    public function getNextWaitingEntries(int $productPriceId, int $limit): \Illuminate\Support\Collection
    {
        $models = WaitlistEntry::query()
            ->where('product_price_id', $productPriceId)
            ->where('status', WaitlistEntryStatus::WAITING->name)
            ->orderBy('position')
            ->limit($limit)
            ->get();

        return $this->handleResults($models);
    }

    public function lockForProductPrice(int $productPriceId): void
    {
        DB::table('waitlist_entries')
            ->where('product_price_id', $productPriceId)
            ->whereIn('status', [WaitlistEntryStatus::WAITING->name, WaitlistEntryStatus::OFFERED->name])
            ->lockForUpdate()
            ->select('id')
            ->get();
    }

    public function findByIdLocked(int $id): ?WaitlistEntryDomainObject
    {
        $model = WaitlistEntry::query()
            ->where('id', $id)
            ->lockForUpdate()
            ->first();

        if ($model === null) {
            return null;
        }

        return $this->handleSingleResult($model);
    }

    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $where = [
            [WaitlistEntryDomainObjectAbstract::EVENT_ID, '=', $eventId],
        ];

        if ($params->query) {
            $where[] = static function (Builder $builder) use ($params) {
                $builder
                    ->where(WaitlistEntryDomainObjectAbstract::FIRST_NAME, 'ilike', '%' . $params->query . '%')
                    ->orWhere(WaitlistEntryDomainObjectAbstract::LAST_NAME, 'ilike', '%' . $params->query . '%')
                    ->orWhere(WaitlistEntryDomainObjectAbstract::EMAIL, 'ilike', '%' . $params->query . '%');
            };
        }

        if (!empty($params->filter_fields)) {
            $this->applyFilterFields($params, WaitlistEntryDomainObject::getAllowedFilterFields());
        }

        $this->model = $this->model->orderBy(
            column: $params->sort_by ?? WaitlistEntryDomainObject::getDefaultSort(),
            direction: $params->sort_direction ?? WaitlistEntryDomainObject::getDefaultSortDirection(),
        );

        return $this->loadRelation(new Relationship(
                domainObject: OrderDomainObject::class,
                name: OrderDomainObjectAbstract::SINGULAR_NAME,
            ))
            ->loadRelation(new Relationship(
                domainObject: ProductPriceDomainObject::class,
                nested: [
                    new Relationship(
                        domainObject: ProductDomainObject::class,
                        name: ProductDomainObjectAbstract::SINGULAR_NAME,
                    ),
                ],
                name: ProductPriceDomainObjectAbstract::SINGULAR_NAME
            ))
            ->paginateWhere(
                where: $where,
                limit: $params->per_page,
                page: $params->page,
            );
    }
}

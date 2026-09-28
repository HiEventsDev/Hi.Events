<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\CashlessSalesPointDomainObject;
use HiEvents\DomainObjects\Enums\CashlessTransactionType;
use HiEvents\DomainObjects\Generated\CashlessSalesPointDomainObjectAbstract;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\CashlessSalesPoint;
use HiEvents\Repository\DTO\CashlessSalesPointStatsDTO;
use HiEvents\Repository\Interfaces\CashlessSalesPointRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<CashlessSalesPointDomainObject>
 */
class CashlessSalesPointRepository extends BaseRepository implements CashlessSalesPointRepositoryInterface
{
    protected function getModel(): string
    {
        return CashlessSalesPoint::class;
    }

    public function getDomainObject(): string
    {
        return CashlessSalesPointDomainObject::class;
    }

    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $where = [
            [CashlessSalesPointDomainObjectAbstract::EVENT_ID, '=', $eventId],
        ];

        if (! empty($params->query)) {
            $where[] = static function (Builder $builder) use ($params) {
                $builder->where(CashlessSalesPointDomainObjectAbstract::NAME, 'ilike', '%'.$params->query.'%');
            };
        }

        $this->model = $this->model->orderBy(
            column: $this->validateSortColumn($params->sort_by, CashlessSalesPointDomainObject::class),
            direction: $this->validateSortDirection($params->sort_direction, CashlessSalesPointDomainObject::class),
        );

        return $this->paginateWhere(
            where: $where,
            limit: $params->per_page,
            page: $params->page,
        );
    }

    public function syncProducts(int $salesPointId, array $productIds): void
    {
        $this->runQuery(function () use ($salesPointId, $productIds) {
            CashlessSalesPoint::find($salesPointId)?->products()->sync(array_unique($productIds));
        });
    }

    public function getStatsByIds(array $salesPointIds): Collection
    {
        if ($salesPointIds === []) {
            return collect();
        }

        $placeholders = implode(',', array_fill(0, count($salesPointIds), '?'));

        $sql = <<<SQL
            SELECT
                t.cashless_sales_point_id,
                COALESCE(SUM(-t.amount), 0) AS sales_total,
                COUNT(*) FILTER (WHERE t.type = ?) AS transaction_count
            FROM cashless_transactions t
                     LEFT JOIN cashless_transactions r ON r.id = t.reverses_transaction_id
            WHERE t.cashless_sales_point_id IN ($placeholders)
              AND (t.type = ? OR (t.type = ? AND r.type = ?))
            GROUP BY t.cashless_sales_point_id;
        SQL;

        $purchase = CashlessTransactionType::PURCHASE->value;

        $rows = $this->db->select($sql, [
            $purchase,
            ...$salesPointIds,
            $purchase,
            CashlessTransactionType::REVERSAL->value,
            $purchase,
        ]);

        return collect($rows)->map(
            static fn ($row) => new CashlessSalesPointStatsDTO(
                salesPointId: (int) $row->cashless_sales_point_id,
                salesTotal: (float) $row->sales_total,
                transactionCount: (int) $row->transaction_count,
            )
        );
    }
}

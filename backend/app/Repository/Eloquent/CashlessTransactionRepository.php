<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\CashlessTransactionDomainObject;
use HiEvents\DomainObjects\Enums\CashlessTransactionType;
use HiEvents\DomainObjects\Generated\AttendeeDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\CashlessTransactionDomainObjectAbstract;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Models\CashlessTransaction;
use HiEvents\Repository\Interfaces\CashlessTransactionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends BaseRepository<CashlessTransactionDomainObject>
 */
class CashlessTransactionRepository extends BaseRepository implements CashlessTransactionRepositoryInterface
{
    protected function getModel(): string
    {
        return CashlessTransaction::class;
    }

    public function getDomainObject(): string
    {
        return CashlessTransactionDomainObject::class;
    }

    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator
    {
        $where = [
            [CashlessTransactionDomainObjectAbstract::EVENT_ID, '=', $eventId],
        ];

        if (! empty($params->query)) {
            $where[] = static function (Builder $builder) use ($params) {
                $builder->whereHas('wallet.attendee', static function (Builder $attendeeBuilder) use ($params) {
                    $attendeeBuilder
                        ->where(AttendeeDomainObjectAbstract::FIRST_NAME, 'ilike', '%'.$params->query.'%')
                        ->orWhere(AttendeeDomainObjectAbstract::LAST_NAME, 'ilike', '%'.$params->query.'%')
                        ->orWhere(AttendeeDomainObjectAbstract::EMAIL, 'ilike', '%'.$params->query.'%')
                        ->orWhere(AttendeeDomainObjectAbstract::PUBLIC_ID, 'ilike', '%'.$params->query.'%');
                });
            };
        }

        $this->applyFilterFields($params, [
            CashlessTransactionDomainObjectAbstract::TYPE,
            CashlessTransactionDomainObjectAbstract::CASHLESS_SALES_POINT_ID,
            CashlessTransactionDomainObjectAbstract::CASHLESS_WALLET_ID,
        ]);

        $this->model = $this->model->orderBy(
            column: $this->validateSortColumn($params->sort_by, CashlessTransactionDomainObject::class),
            direction: $this->validateSortDirection($params->sort_direction, CashlessTransactionDomainObject::class),
        );

        return $this->paginateWhere(
            where: $where,
            limit: $params->per_page,
            page: $params->page,
        );
    }

    public function findByWalletId(int $walletId, int $limit): Collection
    {
        return $this->runQuery(fn () => $this->handleResults(
            $this->model
                ->where(CashlessTransactionDomainObjectAbstract::CASHLESS_WALLET_ID, $walletId)
                ->orderByDesc(CashlessTransactionDomainObjectAbstract::CREATED_AT)
                ->limit($limit)
                ->get()
        ));
    }

    public function findBySalesPointId(int $salesPointId, int $limit): Collection
    {
        return $this->runQuery(fn () => $this->handleResults(
            $this->model
                ->where(CashlessTransactionDomainObjectAbstract::CASHLESS_SALES_POINT_ID, $salesPointId)
                ->orderByDesc(CashlessTransactionDomainObjectAbstract::ID)
                ->limit($limit)
                ->get()
        ));
    }

    public function findCreditingTopupsForRefund(int $walletId): Collection
    {
        return $this->runQuery(fn () => $this->handleResults(
            $this->model
                ->where(CashlessTransactionDomainObjectAbstract::CASHLESS_WALLET_ID, $walletId)
                ->whereIn(CashlessTransactionDomainObjectAbstract::TYPE, [
                    CashlessTransactionType::TOPUP_ONLINE->value,
                    CashlessTransactionType::TOPUP_STAFF->value,
                ])
                ->orderByDesc(CashlessTransactionDomainObjectAbstract::CREATED_AT)
                ->get()
        ));
    }
}

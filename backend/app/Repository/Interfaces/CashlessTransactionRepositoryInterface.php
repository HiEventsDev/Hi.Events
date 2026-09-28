<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\CashlessTransactionDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<CashlessTransactionDomainObject>
 */
interface CashlessTransactionRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;

    /**
     * @return Collection<CashlessTransactionDomainObject>
     */
    public function findByWalletId(int $walletId, int $limit): Collection;

    /**
     * @return Collection<CashlessTransactionDomainObject>
     */
    public function findBySalesPointId(int $salesPointId, int $limit): Collection;

    /**
     * @return Collection<CashlessTransactionDomainObject>
     */
    public function findCreditingTopupsForRefund(int $walletId): Collection;
}

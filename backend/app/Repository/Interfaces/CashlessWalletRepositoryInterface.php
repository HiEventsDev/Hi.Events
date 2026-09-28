<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\CashlessWalletDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<CashlessWalletDomainObject>
 */
interface CashlessWalletRepositoryInterface extends RepositoryInterface
{
    public function findByEventId(int $eventId, QueryParamsDTO $params): LengthAwarePaginator;

    public function lockById(int $walletId): ?CashlessWalletDomainObject;
}

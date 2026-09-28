<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless\Wallet;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CashlessSalesPointDomainObject;
use HiEvents\DomainObjects\CashlessTransactionItemDomainObject;
use HiEvents\DomainObjects\CashlessWalletDomainObject;
use HiEvents\Http\DTO\QueryParamsDTO;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\CashlessTransactionRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class GetCashlessTransactionsHandler
{
    public function __construct(
        private readonly CashlessTransactionRepositoryInterface $transactionRepository,
    ) {}

    public function handle(int $eventId, QueryParamsDTO $params): LengthAwarePaginator
    {
        return $this->transactionRepository
            ->loadRelation(new Relationship(CashlessTransactionItemDomainObject::class, name: 'items'))
            ->loadRelation(new Relationship(CashlessSalesPointDomainObject::class, name: 'sales_point'))
            ->loadRelation(new Relationship(CashlessWalletDomainObject::class, name: 'wallet', nested: [
                new Relationship(AttendeeDomainObject::class, name: 'attendee'),
            ]))
            ->findByEventId($eventId, $params);
    }
}

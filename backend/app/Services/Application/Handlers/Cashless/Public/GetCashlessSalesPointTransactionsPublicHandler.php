<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless\Public;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CashlessTransactionItemDomainObject;
use HiEvents\DomainObjects\CashlessWalletDomainObject;
use HiEvents\Exceptions\CashlessSalesPointAccessException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\CashlessTransactionRepositoryInterface;
use HiEvents\Services\Domain\Cashless\CashlessSalesPointAccessService;
use Illuminate\Support\Collection;

class GetCashlessSalesPointTransactionsPublicHandler
{
    private const TRANSACTION_LIMIT = 200;

    public function __construct(
        private readonly CashlessSalesPointAccessService $salesPointAccessService,
        private readonly CashlessTransactionRepositoryInterface $transactionRepository,
    ) {}

    /**
     * @throws CashlessSalesPointAccessException
     */
    public function handle(string $salesPointShortId, ?string $sessionToken): Collection
    {
        $salesPoint = $this->salesPointAccessService->resolveAuthorised($salesPointShortId, $sessionToken);

        return $this->transactionRepository
            ->loadRelation(new Relationship(CashlessTransactionItemDomainObject::class, name: 'items'))
            ->loadRelation(new Relationship(CashlessWalletDomainObject::class, name: 'wallet', nested: [
                new Relationship(AttendeeDomainObject::class, name: 'attendee'),
            ]))
            ->findBySalesPointId($salesPoint->getId(), self::TRANSACTION_LIMIT);
    }
}

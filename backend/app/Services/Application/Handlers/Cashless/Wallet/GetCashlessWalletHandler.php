<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless\Wallet;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\CashlessSalesPointDomainObject;
use HiEvents\DomainObjects\CashlessTransactionItemDomainObject;
use HiEvents\DomainObjects\CashlessWalletDomainObject;
use HiEvents\DomainObjects\Generated\CashlessWalletDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\CashlessTransactionRepositoryInterface;
use HiEvents\Repository\Interfaces\CashlessWalletRepositoryInterface;

class GetCashlessWalletHandler
{
    private const TRANSACTION_LIMIT = 200;

    public function __construct(
        private readonly CashlessWalletRepositoryInterface $walletRepository,
        private readonly CashlessTransactionRepositoryInterface $transactionRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function handle(int $eventId, int $walletId): CashlessWalletDomainObject
    {
        $wallet = $this->walletRepository
            ->loadRelation(new Relationship(AttendeeDomainObject::class, name: 'attendee'))
            ->findFirstWhere([
                CashlessWalletDomainObjectAbstract::ID => $walletId,
                CashlessWalletDomainObjectAbstract::EVENT_ID => $eventId,
            ]);

        if ($wallet === null) {
            throw new ResourceNotFoundException(__('This cashless wallet could not be found.'));
        }

        return $wallet->setTransactions(
            $this->transactionRepository
                ->loadRelation(new Relationship(CashlessTransactionItemDomainObject::class, name: 'items'))
                ->loadRelation(new Relationship(CashlessSalesPointDomainObject::class, name: 'sales_point'))
                ->findByWalletId($walletId, self::TRANSACTION_LIMIT)
        );
    }
}

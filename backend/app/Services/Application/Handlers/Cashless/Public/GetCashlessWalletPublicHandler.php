<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless\Public;

use HiEvents\DomainObjects\CashlessSalesPointDomainObject;
use HiEvents\DomainObjects\CashlessTransactionItemDomainObject;
use HiEvents\DomainObjects\CashlessWalletDomainObject;
use HiEvents\Exceptions\CashlessNotEnabledException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\CashlessTransactionRepositoryInterface;
use HiEvents\Services\Domain\Cashless\CashlessAttendeeNameMasker;
use HiEvents\Services\Domain\Cashless\CashlessSettingsService;
use HiEvents\Services\Domain\Cashless\CashlessWalletResolveService;

class GetCashlessWalletPublicHandler
{
    private const RECENT_TRANSACTION_LIMIT = 50;

    public function __construct(
        private readonly CashlessSettingsService $cashlessSettingsService,
        private readonly CashlessWalletResolveService $walletResolveService,
        private readonly CashlessTransactionRepositoryInterface $transactionRepository,
        private readonly CashlessAttendeeNameMasker $nameMasker,
    ) {}

    /**
     * @throws CashlessNotEnabledException
     * @throws CashlessWalletUnavailableException
     */
    public function handle(int $eventId, string $ticketReference): CashlessWalletDomainObject
    {
        $this->cashlessSettingsService->getEnabledSettings($eventId);

        $wallet = $this->walletResolveService->resolveByTicketReference($eventId, $ticketReference);

        $this->nameMasker->maskLastName($wallet->getAttendee());

        return $wallet->setTransactions(
            $this->transactionRepository
                ->loadRelation(new Relationship(CashlessTransactionItemDomainObject::class, name: 'items'))
                ->loadRelation(new Relationship(CashlessSalesPointDomainObject::class, name: 'sales_point'))
                ->findByWalletId($wallet->getId(), self::RECENT_TRANSACTION_LIMIT)
        );
    }
}

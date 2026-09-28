<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless\Public;

use HiEvents\DomainObjects\CashlessTransactionItemDomainObject;
use HiEvents\DomainObjects\CashlessWalletDomainObject;
use HiEvents\Exceptions\CashlessSalesPointAccessException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\CashlessTransactionRepositoryInterface;
use HiEvents\Services\Domain\Cashless\CashlessAttendeeNameMasker;
use HiEvents\Services\Domain\Cashless\CashlessSalesPointAccessService;
use HiEvents\Services\Domain\Cashless\CashlessWalletResolveService;

class GetCashlessWalletForSalesPointHandler
{
    private const RECENT_TRANSACTION_LIMIT = 10;

    public function __construct(
        private readonly CashlessSalesPointAccessService $salesPointAccessService,
        private readonly CashlessWalletResolveService $walletResolveService,
        private readonly CashlessTransactionRepositoryInterface $transactionRepository,
        private readonly CashlessAttendeeNameMasker $nameMasker,
    ) {}

    /**
     * @throws CashlessSalesPointAccessException
     * @throws CashlessWalletUnavailableException
     */
    public function handle(
        string $salesPointShortId,
        string $attendeePublicId,
        ?string $sessionToken,
    ): CashlessWalletDomainObject {
        $salesPoint = $this->salesPointAccessService->resolveAuthorised($salesPointShortId, $sessionToken);

        $wallet = $this->walletResolveService->resolveByAttendeePublicId(
            eventId: $salesPoint->getEventId(),
            attendeePublicId: $attendeePublicId,
        );

        if (! $salesPoint->getAllowStaffTopups()) {
            $this->nameMasker->maskLastName($wallet->getAttendee());
        }

        return $wallet->setTransactions(
            $this->transactionRepository
                ->loadRelation(new Relationship(CashlessTransactionItemDomainObject::class, name: 'items'))
                ->findByWalletId($wallet->getId(), self::RECENT_TRANSACTION_LIMIT)
        );
    }
}

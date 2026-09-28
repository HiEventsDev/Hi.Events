<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless\Public;

use Carbon\Carbon;
use HiEvents\DomainObjects\CashlessTransactionDomainObject;
use HiEvents\DomainObjects\Generated\CashlessTransactionDomainObjectAbstract;
use HiEvents\Exceptions\CashlessSalesPointAccessException;
use HiEvents\Exceptions\CashlessTransactionNotReversibleException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Exceptions\InsufficientCashlessBalanceException;
use HiEvents\Repository\Interfaces\CashlessTransactionRepositoryInterface;
use HiEvents\Services\Domain\Cashless\CashlessReversalService;
use HiEvents\Services\Domain\Cashless\CashlessSalesPointAccessService;
use Throwable;

class ReverseCashlessTransactionPublicHandler
{
    private const REVERSAL_WINDOW_MINUTES = 15;

    public function __construct(
        private readonly CashlessSalesPointAccessService $salesPointAccessService,
        private readonly CashlessTransactionRepositoryInterface $transactionRepository,
        private readonly CashlessReversalService $reversalService,
    ) {}

    /**
     * @throws CashlessSalesPointAccessException
     * @throws CashlessTransactionNotReversibleException
     * @throws CashlessWalletUnavailableException
     * @throws InsufficientCashlessBalanceException
     * @throws Throwable
     */
    public function handle(
        string $salesPointShortId,
        string $transactionShortId,
        ?string $sessionToken,
    ): CashlessTransactionDomainObject {
        $salesPoint = $this->salesPointAccessService->resolveAuthorised($salesPointShortId, $sessionToken);

        $transaction = $this->transactionRepository->findFirstWhere([
            CashlessTransactionDomainObjectAbstract::SHORT_ID => $transactionShortId,
            CashlessTransactionDomainObjectAbstract::CASHLESS_SALES_POINT_ID => $salesPoint->getId(),
        ]);

        if ($transaction === null) {
            throw new CashlessTransactionNotReversibleException(
                __('That transaction was not made at this sales point.')
            );
        }

        if (Carbon::parse($transaction->getCreatedAt())->addMinutes(self::REVERSAL_WINDOW_MINUTES)->isPast()) {
            throw new CashlessTransactionNotReversibleException(
                __('This transaction is too old to be reversed here. Please ask the organizer.')
            );
        }

        return $this->reversalService->reverse($transaction, reversedByUserId: null);
    }
}

<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless\Wallet;

use HiEvents\DomainObjects\CashlessTransactionDomainObject;
use HiEvents\DomainObjects\Generated\CashlessTransactionDomainObjectAbstract;
use HiEvents\Exceptions\CashlessTransactionNotReversibleException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Exceptions\InsufficientCashlessBalanceException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\CashlessTransactionRepositoryInterface;
use HiEvents\Services\Domain\Cashless\CashlessReversalService;
use Throwable;

class ReverseCashlessTransactionHandler
{
    public function __construct(
        private readonly CashlessTransactionRepositoryInterface $transactionRepository,
        private readonly CashlessReversalService $reversalService,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws CashlessTransactionNotReversibleException
     * @throws CashlessWalletUnavailableException
     * @throws InsufficientCashlessBalanceException
     * @throws Throwable
     */
    public function handle(
        int $eventId,
        int $transactionId,
        int $userId,
        ?string $notes,
    ): CashlessTransactionDomainObject {
        $transaction = $this->transactionRepository->findFirstWhere([
            CashlessTransactionDomainObjectAbstract::ID => $transactionId,
            CashlessTransactionDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($transaction === null) {
            throw new ResourceNotFoundException(__('This transaction could not be found.'));
        }

        return $this->reversalService->reverse($transaction, reversedByUserId: $userId, notes: $notes);
    }
}

<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Cashless;

use HiEvents\DomainObjects\CashlessTransactionDomainObject;
use HiEvents\DomainObjects\Enums\CashlessTransactionType;
use HiEvents\Exceptions\CashlessTransactionNotReversibleException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Exceptions\InsufficientCashlessBalanceException;
use Throwable;

class CashlessReversalService
{
    public function __construct(
        private readonly CashlessWalletService $walletService,
        private readonly CashlessPosOrderService $posOrderService,
    ) {}

    /**
     * @throws CashlessTransactionNotReversibleException
     * @throws CashlessWalletUnavailableException
     * @throws InsufficientCashlessBalanceException
     * @throws Throwable
     */
    public function reverse(
        CashlessTransactionDomainObject $transaction,
        ?int $reversedByUserId,
        ?string $notes = null,
    ): CashlessTransactionDomainObject {
        $reversal = $this->walletService->reverse($transaction, $reversedByUserId, $notes);

        if ($transaction->getType() === CashlessTransactionType::PURCHASE->value && $transaction->getOrderId() !== null) {
            $this->posOrderService->cancelSale($transaction->getOrderId());
        }

        return $reversal;
    }
}

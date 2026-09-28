<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Cashless;

use HiEvents\DomainObjects\CashlessTransactionDomainObject;
use HiEvents\DomainObjects\CashlessWalletDomainObject;
use HiEvents\DomainObjects\Enums\CashlessTransactionType;
use HiEvents\DomainObjects\Generated\CashlessTransactionDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\CashlessTransactionItemDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\CashlessWalletDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CashlessWalletStatus;
use HiEvents\Exceptions\CashlessTransactionNotReversibleException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Exceptions\InsufficientCashlessBalanceException;
use HiEvents\Helper\Currency;
use HiEvents\Helper\IdHelper;
use HiEvents\Repository\Interfaces\CashlessTransactionItemRepositoryInterface;
use HiEvents\Repository\Interfaces\CashlessTransactionRepositoryInterface;
use HiEvents\Repository\Interfaces\CashlessWalletRepositoryInterface;
use HiEvents\Services\Domain\Cashless\DTO\CashlessTransactionItemDTO;
use HiEvents\Services\Domain\Cashless\DTO\RecordCashlessTransactionDTO;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Throwable;

class CashlessWalletService
{
    public function __construct(
        private readonly CashlessWalletRepositoryInterface $walletRepository,
        private readonly CashlessTransactionRepositoryInterface $transactionRepository,
        private readonly CashlessTransactionItemRepositoryInterface $transactionItemRepository,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws CashlessWalletUnavailableException
     * @throws InsufficientCashlessBalanceException
     * @throws Throwable
     */
    public function record(RecordCashlessTransactionDTO $transactionData): CashlessTransactionDomainObject
    {
        return $this->databaseManager->transaction(function () use ($transactionData) {
            $wallet = $this->lockWallet($transactionData->wallet_id);

            $signedAmount = $this->signAmount($transactionData->type, $transactionData->positive_amount);

            return $this->write(
                wallet: $wallet,
                type: $transactionData->type,
                signedAmount: $signedAmount,
                totalsDelta: $this->totalsDelta($transactionData->type, $signedAmount),
                orderId: $transactionData->order_id,
                salesPointId: $transactionData->sales_point_id,
                createdByUserId: $transactionData->created_by_user_id,
                staffPaymentMethod: $transactionData->staff_payment_method?->value,
                clientReferenceId: $transactionData->client_reference_id,
                notes: $transactionData->notes,
                reversesTransactionId: null,
                items: $transactionData->items,
            );
        });
    }

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
        if ($transaction->isReversal()) {
            throw new CashlessTransactionNotReversibleException(
                __('A reversal cannot itself be reversed.')
            );
        }

        if ($transaction->isClosure()) {
            throw new CashlessTransactionNotReversibleException(
                __('A cashless closure cannot be reversed.')
            );
        }

        $existingReversal = $this->transactionRepository->findFirstWhere([
            CashlessTransactionDomainObjectAbstract::REVERSES_TRANSACTION_ID => $transaction->getId(),
        ]);

        if ($existingReversal !== null) {
            throw new CashlessTransactionNotReversibleException(
                __('This transaction has already been reversed.')
            );
        }

        return $this->databaseManager->transaction(function () use ($transaction, $reversedByUserId, $notes) {
            $wallet = $this->lockWallet($transaction->getCashlessWalletId(), requireActive: false);

            $signedAmount = -$transaction->getAmount();
            $originalType = CashlessTransactionType::from($transaction->getType());

            return $this->write(
                wallet: $wallet,
                type: CashlessTransactionType::REVERSAL,
                signedAmount: $signedAmount,
                totalsDelta: $this->invertDelta(
                    $this->totalsDelta($originalType, $transaction->getAmount())
                ),
                orderId: null,
                salesPointId: $transaction->getCashlessSalesPointId(),
                createdByUserId: $reversedByUserId,
                staffPaymentMethod: null,
                clientReferenceId: null,
                notes: $notes,
                reversesTransactionId: $transaction->getId(),
                items: null,
            );
        });
    }

    /**
     * @throws CashlessWalletUnavailableException
     * @throws Throwable
     */
    public function close(int $walletId, ?int $closedByUserId): ?CashlessTransactionDomainObject
    {
        return $this->databaseManager->transaction(function () use ($walletId, $closedByUserId) {
            $wallet = $this->lockWallet($walletId, requireActive: false);

            $transaction = $wallet->getBalance() > 0
                ? $this->write(
                    wallet: $wallet,
                    type: CashlessTransactionType::CLOSURE,
                    signedAmount: -$wallet->getBalance(),
                    totalsDelta: $this->totalsDelta(CashlessTransactionType::CLOSURE, $wallet->getBalance()),
                    orderId: null,
                    salesPointId: null,
                    createdByUserId: $closedByUserId,
                    staffPaymentMethod: null,
                    clientReferenceId: null,
                    notes: null,
                    reversesTransactionId: null,
                    items: null,
                )
                : null;

            $this->walletRepository->updateFromArray($wallet->getId(), [
                CashlessWalletDomainObjectAbstract::STATUS => CashlessWalletStatus::CLOSED->value,
            ]);

            return $transaction;
        });
    }

    /**
     * @throws CashlessWalletUnavailableException
     */
    private function lockWallet(int $walletId, bool $requireActive = true): CashlessWalletDomainObject
    {
        $wallet = $this->walletRepository->lockById($walletId);

        if ($wallet === null) {
            throw new CashlessWalletUnavailableException(
                __('This cashless wallet could not be found.')
            );
        }

        if ($requireActive && ! $wallet->isActive()) {
            throw new CashlessWalletUnavailableException(
                __('This cashless wallet is not active.')
            );
        }

        return $wallet;
    }

    /**
     * @param  Collection<CashlessTransactionItemDTO>|null  $items
     *
     * @throws InsufficientCashlessBalanceException
     */
    private function write(
        CashlessWalletDomainObject $wallet,
        CashlessTransactionType $type,
        float $signedAmount,
        array $totalsDelta,
        ?int $orderId,
        ?int $salesPointId,
        ?int $createdByUserId,
        ?string $staffPaymentMethod,
        ?string $clientReferenceId,
        ?string $notes,
        ?int $reversesTransactionId,
        ?Collection $items,
    ): CashlessTransactionDomainObject {
        $newBalance = Currency::round($wallet->getBalance() + $signedAmount);

        if ($newBalance < 0) {
            throw new InsufficientCashlessBalanceException(
                __('The cashless balance is too low for this transaction.')
            );
        }

        $this->walletRepository->updateFromArray($wallet->getId(), [
            CashlessWalletDomainObjectAbstract::BALANCE => $newBalance,
            CashlessWalletDomainObjectAbstract::TOTAL_TOPPED_UP => Currency::round(
                $wallet->getTotalToppedUp() + $totalsDelta['topped_up']
            ),
            CashlessWalletDomainObjectAbstract::TOTAL_SPENT => Currency::round(
                $wallet->getTotalSpent() + $totalsDelta['spent']
            ),
            CashlessWalletDomainObjectAbstract::TOTAL_REFUNDED => Currency::round(
                $wallet->getTotalRefunded() + $totalsDelta['refunded']
            ),
        ]);

        $transaction = $this->transactionRepository->create([
            CashlessTransactionDomainObjectAbstract::SHORT_ID => IdHelper::shortId(IdHelper::CASHLESS_TRANSACTION_PREFIX),
            CashlessTransactionDomainObjectAbstract::CASHLESS_WALLET_ID => $wallet->getId(),
            CashlessTransactionDomainObjectAbstract::EVENT_ID => $wallet->getEventId(),
            CashlessTransactionDomainObjectAbstract::TYPE => $type->value,
            CashlessTransactionDomainObjectAbstract::AMOUNT => Currency::round($signedAmount),
            CashlessTransactionDomainObjectAbstract::BALANCE_AFTER => $newBalance,
            CashlessTransactionDomainObjectAbstract::ORDER_ID => $orderId,
            CashlessTransactionDomainObjectAbstract::CASHLESS_SALES_POINT_ID => $salesPointId,
            CashlessTransactionDomainObjectAbstract::CREATED_BY_USER_ID => $createdByUserId,
            CashlessTransactionDomainObjectAbstract::REVERSES_TRANSACTION_ID => $reversesTransactionId,
            CashlessTransactionDomainObjectAbstract::STAFF_PAYMENT_METHOD => $staffPaymentMethod,
            CashlessTransactionDomainObjectAbstract::CLIENT_REFERENCE_ID => $clientReferenceId,
            CashlessTransactionDomainObjectAbstract::NOTES => $notes,
        ]);

        if ($items !== null && $items->isNotEmpty()) {
            $this->transactionItemRepository->insert(
                $items->map(static fn (CashlessTransactionItemDTO $item) => [
                    CashlessTransactionItemDomainObjectAbstract::CASHLESS_TRANSACTION_ID => $transaction->getId(),
                    CashlessTransactionItemDomainObjectAbstract::PRODUCT_ID => $item->product_id,
                    CashlessTransactionItemDomainObjectAbstract::PRODUCT_PRICE_ID => $item->product_price_id,
                    CashlessTransactionItemDomainObjectAbstract::PRODUCT_TITLE => $item->product_title,
                    CashlessTransactionItemDomainObjectAbstract::UNIT_PRICE => $item->unit_price,
                    CashlessTransactionItemDomainObjectAbstract::QUANTITY => $item->quantity,
                    CashlessTransactionItemDomainObjectAbstract::TOTAL => $item->total,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->toArray()
            );
        }

        return $transaction;
    }

    private function signAmount(CashlessTransactionType $type, float $amount): float
    {
        return $type->isCredit() ? abs($amount) : -abs($amount);
    }

    /**
     * @return array{topped_up: float, spent: float, refunded: float}
     */
    private function totalsDelta(CashlessTransactionType $type, float $amount): array
    {
        $magnitude = abs($amount);

        return match ($type) {
            CashlessTransactionType::TOPUP_ONLINE,
            CashlessTransactionType::TOPUP_STAFF => ['topped_up' => $magnitude, 'spent' => 0.0, 'refunded' => 0.0],
            CashlessTransactionType::PURCHASE => ['topped_up' => 0.0, 'spent' => $magnitude, 'refunded' => 0.0],
            CashlessTransactionType::REFUND_REMAINING => ['topped_up' => 0.0, 'spent' => 0.0, 'refunded' => $magnitude],
            CashlessTransactionType::REVERSAL,
            CashlessTransactionType::CLOSURE => ['topped_up' => 0.0, 'spent' => 0.0, 'refunded' => 0.0],
        };
    }

    /**
     * @param  array{topped_up: float, spent: float, refunded: float}  $delta
     * @return array{topped_up: float, spent: float, refunded: float}
     */
    private function invertDelta(array $delta): array
    {
        return array_map(static fn (float $value) => -$value, $delta);
    }
}

<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless\Wallet;

use HiEvents\DomainObjects\CashlessTransactionDomainObject;
use HiEvents\DomainObjects\CashlessWalletDomainObject;
use HiEvents\DomainObjects\Enums\CashlessRefundMethod;
use HiEvents\DomainObjects\Enums\CashlessTransactionType;
use HiEvents\Exceptions\CashlessNotEnabledException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Exceptions\RefundNotPossibleException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Helper\Currency;
use HiEvents\Repository\Interfaces\CashlessTransactionRepositoryInterface;
use HiEvents\Services\Application\Handlers\Cashless\DTO\RefundCashlessWalletResultDTO;
use HiEvents\Services\Application\Handlers\Order\DTO\RefundOrderDTO;
use HiEvents\Services\Application\Handlers\Order\RefundOrderHandler;
use HiEvents\Services\Domain\Cashless\CashlessSettingsService;
use HiEvents\Services\Domain\Cashless\CashlessWalletService;
use HiEvents\Services\Domain\Cashless\DTO\RecordCashlessTransactionDTO;
use Throwable;

class RefundCashlessWalletHandler
{
    public function __construct(
        private readonly CashlessSettingsService $cashlessSettingsService,
        private readonly CashlessTransactionRepositoryInterface $transactionRepository,
        private readonly CashlessWalletService $walletService,
        private readonly RefundOrderHandler $refundOrderHandler,
        private readonly GetCashlessWalletHandler $getCashlessWalletHandler,
    ) {}

    /**
     * @throws CashlessNotEnabledException
     * @throws CashlessWalletUnavailableException
     * @throws RefundNotPossibleException
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function handle(
        int $eventId,
        int $walletId,
        CashlessRefundMethod $method,
        int $userId,
    ): RefundCashlessWalletResultDTO {
        $settings = $this->cashlessSettingsService->getEnabledSettings($eventId);

        if (! $this->cashlessSettingsService->isRefundWindowOpen($settings)) {
            throw new RefundNotPossibleException(
                __('Balance refunds are not open for this event.')
            );
        }

        $wallet = $this->getCashlessWalletHandler->handle($eventId, $walletId);

        if ($wallet->getBalance() <= 0) {
            throw new RefundNotPossibleException(__('This balance is already empty.'));
        }

        $refundedAmount = $method === CashlessRefundMethod::ORIGINAL_PAYMENT
            ? $this->refundToOriginalPayments($eventId, $wallet)
            : $wallet->getBalance();

        if ($refundedAmount > 0) {
            $this->recordRefund($wallet, $refundedAmount, $userId, $method);
        }

        return new RefundCashlessWalletResultDTO(
            refunded_amount: $refundedAmount,
            unrefundable_amount: Currency::round($wallet->getBalance() - $refundedAmount),
        );
    }

    /**
     * @throws Throwable
     */
    private function refundToOriginalPayments(int $eventId, CashlessWalletDomainObject $wallet): float
    {
        $remaining = $wallet->getBalance();
        $refunded = 0.0;

        $topups = $this->transactionRepository
            ->findCreditingTopupsForRefund($wallet->getId())
            ->filter(fn (CashlessTransactionDomainObject $topup) => $topup->getOrderId() !== null);

        foreach ($topups as $topup) {
            if ($remaining <= 0) {
                break;
            }

            $amount = Currency::round(min($remaining, $topup->getAmount()));

            $this->refundOrderHandler->handle(new RefundOrderDTO(
                event_id: $eventId,
                order_id: $topup->getOrderId(),
                amount: $amount,
                notify_buyer: true,
                cancel_order: false,
            ));

            $remaining = Currency::round($remaining - $amount);
            $refunded = Currency::round($refunded + $amount);
        }

        return $refunded;
    }

    /**
     * @throws Throwable
     */
    private function recordRefund(
        CashlessWalletDomainObject $wallet,
        float $amount,
        int $userId,
        CashlessRefundMethod $method,
    ): void {
        $this->walletService->record(new RecordCashlessTransactionDTO(
            wallet_id: $wallet->getId(),
            type: CashlessTransactionType::REFUND_REMAINING,
            positive_amount: $amount,
            created_by_user_id: $userId,
            notes: __('Balance refunded via :method', ['method' => $method->value]),
        ));
    }
}

<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless\Wallet;

use HiEvents\DomainObjects\CashlessTransactionDomainObject;
use HiEvents\DomainObjects\Enums\CashlessStaffPaymentMethod;
use HiEvents\DomainObjects\Enums\CashlessTransactionType;
use HiEvents\Exceptions\CashlessNotEnabledException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Services\Domain\Cashless\CashlessSettingsService;
use HiEvents\Services\Domain\Cashless\CashlessWalletService;
use HiEvents\Services\Domain\Cashless\DTO\RecordCashlessTransactionDTO;
use Throwable;

class CreateOrganizerTopupHandler
{
    public function __construct(
        private readonly CashlessSettingsService $cashlessSettingsService,
        private readonly CashlessWalletService $walletService,
        private readonly GetCashlessWalletHandler $getCashlessWalletHandler,
    ) {}

    /**
     * @throws CashlessNotEnabledException
     * @throws CashlessWalletUnavailableException
     * @throws Throwable
     */
    public function handle(
        int $eventId,
        int $walletId,
        float $amount,
        CashlessStaffPaymentMethod $paymentMethod,
        int $userId,
        ?string $notes,
    ): CashlessTransactionDomainObject {
        $this->cashlessSettingsService->getEnabledSettings($eventId);

        $wallet = $this->getCashlessWalletHandler->handle($eventId, $walletId);

        return $this->walletService->record(new RecordCashlessTransactionDTO(
            wallet_id: $wallet->getId(),
            type: CashlessTransactionType::TOPUP_STAFF,
            positive_amount: $amount,
            created_by_user_id: $userId,
            staff_payment_method: $paymentMethod,
            notes: $notes,
        ));
    }
}

<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless\Public;

use HiEvents\DomainObjects\CashlessTransactionDomainObject;
use HiEvents\DomainObjects\Enums\CashlessTransactionType;
use HiEvents\DomainObjects\Generated\CashlessTransactionDomainObjectAbstract;
use HiEvents\Exceptions\CashlessNotEnabledException;
use HiEvents\Exceptions\CashlessSalesPointAccessException;
use HiEvents\Exceptions\CashlessWalletUnavailableException;
use HiEvents\Repository\Interfaces\CashlessTransactionRepositoryInterface;
use HiEvents\Services\Application\Handlers\Cashless\DTO\CreateStaffTopupDTO;
use HiEvents\Services\Domain\Cashless\CashlessSalesPointAccessService;
use HiEvents\Services\Domain\Cashless\CashlessSettingsService;
use HiEvents\Services\Domain\Cashless\CashlessWalletResolveService;
use HiEvents\Services\Domain\Cashless\CashlessWalletService;
use HiEvents\Services\Domain\Cashless\DTO\RecordCashlessTransactionDTO;
use Throwable;

class CreateStaffTopupPublicHandler
{
    public function __construct(
        private readonly CashlessSalesPointAccessService $salesPointAccessService,
        private readonly CashlessSettingsService $cashlessSettingsService,
        private readonly CashlessWalletResolveService $walletResolveService,
        private readonly CashlessWalletService $walletService,
        private readonly CashlessTransactionRepositoryInterface $transactionRepository,
    ) {}

    /**
     * @throws CashlessSalesPointAccessException
     * @throws CashlessNotEnabledException
     * @throws CashlessWalletUnavailableException
     * @throws Throwable
     */
    public function handle(CreateStaffTopupDTO $topupData): CashlessTransactionDomainObject
    {
        $salesPoint = $this->salesPointAccessService->resolveAuthorised(
            shortId: $topupData->sales_point_short_id,
            token: $topupData->session_token,
        );

        if (! $salesPoint->getAllowStaffTopups()) {
            throw new CashlessSalesPointAccessException(
                __('This sales point is not allowed to top up balances.')
            );
        }

        $this->cashlessSettingsService->getEnabledSettings($salesPoint->getEventId());

        $alreadyRecorded = $this->transactionRepository->findFirstWhere([
            CashlessTransactionDomainObjectAbstract::CASHLESS_SALES_POINT_ID => $salesPoint->getId(),
            CashlessTransactionDomainObjectAbstract::CLIENT_REFERENCE_ID => $topupData->client_reference_id,
        ]);

        if ($alreadyRecorded !== null) {
            return $alreadyRecorded;
        }

        $wallet = $this->walletResolveService->resolveByAttendeePublicId(
            eventId: $salesPoint->getEventId(),
            attendeePublicId: $topupData->attendee_public_id,
        );

        return $this->walletService->record(new RecordCashlessTransactionDTO(
            wallet_id: $wallet->getId(),
            type: CashlessTransactionType::TOPUP_STAFF,
            positive_amount: $topupData->amount,
            sales_point_id: $salesPoint->getId(),
            staff_payment_method: $topupData->payment_method,
            client_reference_id: $topupData->client_reference_id,
            notes: $topupData->notes,
        ));
    }
}

<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Cashless;

use HiEvents\DomainObjects\CashlessWalletDomainObject;
use HiEvents\DomainObjects\Generated\CashlessWalletDomainObjectAbstract;
use HiEvents\DomainObjects\Generated\EventSettingDomainObjectAbstract;
use HiEvents\DomainObjects\Status\CashlessWalletStatus;
use HiEvents\Exceptions\CashlessClosureNotAllowedException;
use HiEvents\Exceptions\CashlessNotEnabledException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Helper\Currency;
use HiEvents\Repository\Interfaces\CashlessWalletRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Services\Domain\Cashless\DTO\CashlessClosureResultDTO;
use HiEvents\Services\Domain\EventStatistics\EventStatisticsCashlessClosureService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Throwable;

class CashlessClosureService
{
    public function __construct(
        private readonly CashlessSettingsService $settingsService,
        private readonly CashlessWalletRepositoryInterface $walletRepository,
        private readonly CashlessWalletService $walletService,
        private readonly CashlessOccurrenceResolver $occurrenceResolver,
        private readonly EventStatisticsCashlessClosureService $statisticsService,
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws CashlessClosureNotAllowedException
     * @throws CashlessNotEnabledException
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function close(int $eventId, ?int $closedByUserId): CashlessClosureResultDTO
    {
        return $this->databaseManager->transaction(function () use ($eventId, $closedByUserId) {
            $settings = $this->settingsService->getEnabledSettings($eventId);

            if ($settings->getCashlessClosedAt() !== null) {
                throw new CashlessClosureNotAllowedException(
                    __('Cashless has already been closed for this event.')
                );
            }

            if ($this->settingsService->isRefundWindowOpen($settings)) {
                throw new CashlessClosureNotAllowedException(
                    __('Balance refunds are still open. Turn them off or wait for the refund deadline before closing cashless.')
                );
            }

            $walletsWithBalance = $this->walletRepository->findWhere([
                [CashlessWalletDomainObjectAbstract::EVENT_ID, '=', $eventId],
                [CashlessWalletDomainObjectAbstract::BALANCE, '>', 0],
            ]);

            $amountClosed = 0.0;

            /** @var CashlessWalletDomainObject $wallet */
            foreach ($walletsWithBalance as $wallet) {
                $transaction = $this->walletService->close($wallet->getId(), $closedByUserId);
                $amountClosed += abs($transaction->getAmount());
            }

            $amountClosed = Currency::round($amountClosed);

            $this->walletRepository->updateWhere(
                attributes: [CashlessWalletDomainObjectAbstract::STATUS => CashlessWalletStatus::CLOSED->value],
                where: [CashlessWalletDomainObjectAbstract::EVENT_ID => $eventId],
            );

            if ($amountClosed > 0) {
                $this->statisticsService->recordClosedBalance(
                    eventId: $eventId,
                    occurrenceId: $this->occurrenceResolver->resolveForSale($eventId),
                    date: Carbon::now()->format('Y-m-d'),
                    amount: $amountClosed,
                );
            }

            $this->eventSettingsRepository->updateWhere(
                attributes: [EventSettingDomainObjectAbstract::CASHLESS_CLOSED_AT => Carbon::now()],
                where: [EventSettingDomainObjectAbstract::EVENT_ID => $eventId],
            );

            return new CashlessClosureResultDTO(
                wallets_closed: $walletsWithBalance->count(),
                amount_closed: $amountClosed,
            );
        });
    }
}

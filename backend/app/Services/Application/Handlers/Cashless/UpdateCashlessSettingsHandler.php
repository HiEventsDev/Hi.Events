<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Cashless;

use HiEvents\DomainObjects\Generated\EventSettingDomainObjectAbstract;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Helper\DateHelper;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;
use HiEvents\Services\Application\Handlers\Cashless\DTO\CashlessSettingsDTO;
use HiEvents\Services\Application\Handlers\Cashless\DTO\UpdateCashlessSettingsDTO;
use HiEvents\Services\Domain\Cashless\CashlessSettingsService;
use HiEvents\Services\Domain\Cashless\CashlessTopupProductProvisionService;
use HiEvents\Services\Domain\Tax\DTO\TaxAndProductAssociateParams;
use HiEvents\Services\Domain\Tax\TaxAndProductAssociationService;
use Illuminate\Database\DatabaseManager;
use Throwable;

class UpdateCashlessSettingsHandler
{
    public function __construct(
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly CashlessTopupProductProvisionService $topupProductProvisionService,
        private readonly GetCashlessSettingsHandler $getCashlessSettingsHandler,
        private readonly CashlessSettingsService $cashlessSettingsService,
        private readonly TaxAndProductAssociationService $taxAndProductAssociationService,
        private readonly DatabaseManager $databaseManager,
    ) {}

    /**
     * @throws ResourceNotFoundException
     * @throws Throwable
     */
    public function handle(UpdateCashlessSettingsDTO $settingsData): CashlessSettingsDTO
    {
        return $this->databaseManager->transaction(function () use ($settingsData) {
            $existingSettings = $this->cashlessSettingsService->getSettings($settingsData->event_id);
            $event = $this->eventRepository->findById($settingsData->event_id);

            $this->eventSettingsRepository->updateWhere(
                attributes: [
                    EventSettingDomainObjectAbstract::CASHLESS_ENABLED => $settingsData->cashless_enabled,
                    EventSettingDomainObjectAbstract::CASHLESS_MIN_TOPUP_AMOUNT => $settingsData->cashless_min_topup_amount,
                    EventSettingDomainObjectAbstract::CASHLESS_ALLOW_REMAINING_BALANCE_REFUND => $settingsData->cashless_allow_remaining_balance_refund,
                    EventSettingDomainObjectAbstract::CASHLESS_ONLINE_TOPUP_ENABLED => $settingsData->cashless_online_topup_enabled,
                    EventSettingDomainObjectAbstract::CASHLESS_REFUND_DEADLINE_AT => $settingsData->cashless_refund_deadline_at
                        ? DateHelper::convertToUTC($settingsData->cashless_refund_deadline_at, $event->getTimezone())
                        : null,
                ],
                where: [EventSettingDomainObjectAbstract::EVENT_ID => $settingsData->event_id],
            );

            if ($settingsData->cashless_enabled) {
                $this->topupProductProvisionService->provision(
                    event: $event,
                    eventSettings: $existingSettings,
                    minimumTopupAmount: $settingsData->cashless_min_topup_amount,
                );

                $this->taxAndProductAssociationService->addTaxesToProduct(
                    new TaxAndProductAssociateParams(
                        productId: $this->cashlessSettingsService->getSettings($settingsData->event_id)->getCashlessTopupProductId(),
                        accountId: $event->getAccountId(),
                        taxAndFeeIds: $settingsData->cashless_topup_tax_and_fee_ids,
                    ),
                );
            }

            return $this->getCashlessSettingsHandler->handle($settingsData->event_id);
        });
    }
}

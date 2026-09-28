<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Cashless;

use Carbon\Carbon;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\EventSettingDomainObjectAbstract;
use HiEvents\Exceptions\CashlessNotEnabledException;
use HiEvents\Exceptions\ResourceNotFoundException;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;

class CashlessSettingsService
{
    public function __construct(
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,
    ) {}

    /**
     * @throws ResourceNotFoundException
     */
    public function getSettings(int $eventId): EventSettingDomainObject
    {
        $settings = $this->eventSettingsRepository->findFirstWhere([
            EventSettingDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        if ($settings === null) {
            throw new ResourceNotFoundException(
                __('Settings for event :id could not be found.', ['id' => $eventId])
            );
        }

        return $settings;
    }

    /**
     * @throws CashlessNotEnabledException
     * @throws ResourceNotFoundException
     */
    public function getEnabledSettings(int $eventId): EventSettingDomainObject
    {
        $settings = $this->getSettings($eventId);

        if (! $settings->getCashlessEnabled()) {
            throw new CashlessNotEnabledException(
                __('Cashless payments are not enabled for this event.')
            );
        }

        return $settings;
    }

    public function isRefundWindowOpen(EventSettingDomainObject $settings): bool
    {
        if (! $settings->getCashlessAllowRemainingBalanceRefund()) {
            return false;
        }

        $deadline = $settings->getCashlessRefundDeadlineAt();

        return $deadline === null || Carbon::parse($deadline)->isFuture();
    }
}

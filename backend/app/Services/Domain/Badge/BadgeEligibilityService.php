<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Badge;

use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\EventSettingDomainObjectAbstract;
use HiEvents\Repository\Interfaces\EventSettingsRepositoryInterface;

class BadgeEligibilityService
{
    public function __construct(
        private readonly EventSettingsRepositoryInterface $eventSettingsRepository,
    ) {}

    public function includesAwaitingPayment(int $eventId): bool
    {
        /** @var EventSettingDomainObject|null $settings */
        $settings = $this->eventSettingsRepository->findFirstWhere([
            EventSettingDomainObjectAbstract::EVENT_ID => $eventId,
        ]);

        return (bool) $settings?->getAllowOrdersAwaitingOfflinePaymentToCheckIn();
    }
}

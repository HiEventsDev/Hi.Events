<?php

declare(strict_types=1);

namespace HiEvents\Services\Domain\Order;

use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Enums\PhoneCollection;
use HiEvents\DomainObjects\Enums\SmsCountry;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\Generated\CrmSmsConnectionDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmSmsConnectionRepositoryInterface;
use HiEvents\Repository\Interfaces\EventRepositoryInterface;
use HiEvents\Services\Domain\FeatureFlag\FeatureFlagService;

class CheckoutSmsOptInService
{
    public function __construct(
        private readonly FeatureFlagService $featureFlagService,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly CrmSmsConnectionRepositoryInterface $smsConnectionRepository,
    ) {}

    public function isOffered(EventSettingDomainObject $eventSettings): bool
    {
        if (! $eventSettings->getShowSmsOptIn() || ! PhoneCollection::from($eventSettings->getPhoneCollection())->isCollected()) {
            return false;
        }

        /** @var EventDomainObject $event */
        $event = $this->eventRepository->findById($eventSettings->getEventId());
        $flags = $this->featureFlagService->getEnabledFlagsForAccount($event->getAccountId());

        return $flags[FeatureFlag::CRM->value]
            && $flags[FeatureFlag::CRM_SMS->value]
            && $this->smsConnectionRepository->findFirstWhere([CrmSmsConnectionDomainObjectAbstract::ORGANIZER_ID => $event->getOrganizerId()]) !== null;
    }

    public function isRecordedFor(EventSettingDomainObject $eventSettings, ?string $phoneCountry): bool
    {
        return $phoneCountry !== null
            && SmsCountry::tryFrom($phoneCountry) !== null
            && $this->isOffered($eventSettings);
    }
}

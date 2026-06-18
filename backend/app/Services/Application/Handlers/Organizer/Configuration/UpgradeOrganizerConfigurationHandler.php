<?php

namespace HiEvents\Services\Application\Handlers\Organizer\Configuration;

use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\Exceptions\PlanChangeNotAllowedException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrganizerConfigurationRepositoryInterface;
use HiEvents\Services\Domain\Organizer\OrganizerPlanChangeService;
use Throwable;

class UpgradeOrganizerConfigurationHandler
{
    public function __construct(
        private readonly OrganizerConfigurationRepositoryInterface $configurationRepository,
        private readonly OrganizerPlanChangeService $planChangeService,
    ) {}

    /**
     * @throws PlanChangeNotAllowedException
     * @throws Throwable
     */
    public function handle(int $organizerId, int $accountId): OrganizerConfigurationDomainObject
    {
        $currentConfiguration = $this->planChangeService->getCurrentConfiguration($organizerId, $accountId);

        if ($currentConfiguration->getBypassApplicationFees()) {
            throw new PlanChangeNotAllowedException(
                __('Plan changes are not available because platform fees are disabled for this account.'),
            );
        }

        if ($currentConfiguration->getUpgradesToId() === null) {
            throw new PlanChangeNotAllowedException(__('There is no upgrade available for your current plan.'));
        }

        $target = $this->configurationRepository->findById($currentConfiguration->getUpgradesToId());

        $this->planChangeService->changeToPlan($organizerId, $target, enableEntitledFeatures: true);

        return $this->configurationRepository
            ->loadRelation(new Relationship(
                domainObject: OrganizerConfigurationDomainObject::class,
                name: 'upgrades_to',
            ))
            ->loadRelation(new Relationship(
                domainObject: OrganizerConfigurationDomainObject::class,
                name: 'downgrade_options',
            ))
            ->findById($target->getId());
    }
}

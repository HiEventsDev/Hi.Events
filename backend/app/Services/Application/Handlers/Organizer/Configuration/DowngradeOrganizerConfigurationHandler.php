<?php

namespace HiEvents\Services\Application\Handlers\Organizer\Configuration;

use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\Exceptions\PlanChangeNotAllowedException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrganizerConfigurationRepositoryInterface;
use HiEvents\Services\Domain\Organizer\OrganizerPlanChangeService;
use Throwable;

class DowngradeOrganizerConfigurationHandler
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

        $target = $this->configurationRepository
            ->loadRelation(new Relationship(
                domainObject: OrganizerConfigurationDomainObject::class,
                name: 'downgrade_options',
            ))
            ->findById($currentConfiguration->getId())
            ->getDowngradeTarget();

        if ($target === null) {
            throw new PlanChangeNotAllowedException(__('There is no downgrade available for your current plan.'));
        }

        $this->planChangeService->changeToPlan($organizerId, $target);

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

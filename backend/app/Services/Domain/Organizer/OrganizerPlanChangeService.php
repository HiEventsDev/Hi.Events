<?php

namespace HiEvents\Services\Domain\Organizer;

use HiEvents\DomainObjects\OrganizerConfigurationDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use HiEvents\Exceptions\PlanChangeNotAllowedException;
use HiEvents\Repository\Eloquent\Value\Relationship;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Throwable;

class OrganizerPlanChangeService
{
    public function __construct(
        private readonly OrganizerRepositoryInterface $organizerRepository,
        private readonly OrganizerPlanEntitlementService $entitlementService,
        private readonly DatabaseManager $databaseManager,
        private readonly Repository $config,
    ) {}

    /**
     * @throws PlanChangeNotAllowedException
     */
    public function getCurrentConfiguration(int $organizerId, int $accountId): OrganizerConfigurationDomainObject
    {
        if (! $this->config->get('app.saas_mode_enabled')) {
            throw new PlanChangeNotAllowedException(__('Plan changes are not available on this instance.'));
        }

        /** @var OrganizerDomainObject|null $organizer */
        $organizer = $this->organizerRepository
            ->loadRelation(new Relationship(
                domainObject: OrganizerConfigurationDomainObject::class,
                name: 'organizer_configuration',
            ))
            ->findFirstWhere([
                'id' => $organizerId,
                'account_id' => $accountId,
            ]);

        $configuration = $organizer?->getOrganizerConfiguration();

        if ($configuration === null) {
            throw new PlanChangeNotAllowedException(__('There is no plan assigned to this organizer.'));
        }

        return $configuration;
    }

    /**
     * @throws Throwable
     */
    public function changeToPlan(
        int $organizerId,
        OrganizerConfigurationDomainObject $target,
        bool $enableEntitledFeatures = false,
    ): void {
        $this->databaseManager->transaction(function () use ($organizerId, $target, $enableEntitledFeatures) {
            $this->organizerRepository->updateFromArray(
                id: $organizerId,
                attributes: ['organizer_configuration_id' => $target->getId()],
            );

            $this->entitlementService->syncOrganizerSettingsWithPlan(
                $organizerId,
                $target,
                enableEntitledFeatures: $enableEntitledFeatures,
            );
        });
    }
}

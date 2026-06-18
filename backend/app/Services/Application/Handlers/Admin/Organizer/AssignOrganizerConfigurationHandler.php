<?php

declare(strict_types=1);

namespace HiEvents\Services\Application\Handlers\Admin\Organizer;

use HiEvents\Repository\Interfaces\OrganizerConfigurationRepositoryInterface;
use HiEvents\Repository\Interfaces\OrganizerRepositoryInterface;
use HiEvents\Services\Domain\Organizer\OrganizerPlanEntitlementService;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

class AssignOrganizerConfigurationHandler
{
    public function __construct(
        private readonly OrganizerRepositoryInterface              $organizerRepository,
        private readonly OrganizerConfigurationRepositoryInterface $configurationRepository,
        private readonly OrganizerPlanEntitlementService           $entitlementService,
        private readonly DatabaseManager                           $databaseManager,
    )
    {
    }

    /**
     * @throws ModelNotFoundException
     * @throws Throwable
     */
    public function handle(int $organizerId, int $configurationId): void
    {
        $configuration = $this->configurationRepository->findById($configurationId);

        $this->databaseManager->transaction(function () use ($organizerId, $configurationId, $configuration) {
            $this->organizerRepository->updateFromArray(
                id: $organizerId,
                attributes: ['organizer_configuration_id' => $configurationId],
            );

            $this->entitlementService->syncOrganizerSettingsWithPlan($organizerId, $configuration);
        });
    }
}

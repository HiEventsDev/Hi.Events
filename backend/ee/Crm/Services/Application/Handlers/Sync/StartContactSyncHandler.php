<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Application\Handlers\Sync;

use HiEvents\DomainObjects\Generated\CrmOrganizerStateDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmOrganizerStateRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Sync\DTO\ContactSyncStatusDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Projection\ContactReconcileService;
use HiEvents\Enterprise\Crm\Services\Domain\Projection\ContactSyncStarter;
use HiEvents\Enterprise\Crm\Services\Domain\Projection\ContactSyncStatusService;
use HiEvents\Enterprise\Licensing\LicensedFeatureUsageService;

class StartContactSyncHandler
{
    public function __construct(
        private readonly CrmOrganizerStateRepositoryInterface $organizerStateRepository,
        private readonly ContactSyncStatusService $syncStatusService,
        private readonly ContactSyncStarter $syncStarter,
        private readonly LicensedFeatureUsageService $featureUsage,
    ) {}

    public function handle(int $accountId, int $organizerId): ContactSyncStatusDTO
    {
        $state = $this->organizerStateRepository->createIfMissing($accountId, $organizerId);
        $this->featureUsage->forget();

        $status = $this->syncStatusService->getStatus($organizerId);

        if ($status->status === ContactSyncStatusDTO::SYNCING) {
            return $status;
        }

        if ($state->getReconciledUntil() === null) {
            $this->organizerStateRepository->updateWhere(
                attributes: [CrmOrganizerStateDomainObjectAbstract::RECONCILED_UNTIL => now()->subSeconds(ContactReconcileService::SETTLE_SECONDS)->toDateTimeString()],
                where: [CrmOrganizerStateDomainObjectAbstract::ID => $state->getId()],
            );
        }

        $this->syncStarter->start($this->organizerStateRepository->findById($state->getId()));

        return $this->syncStatusService->getStatus($organizerId);
    }
}

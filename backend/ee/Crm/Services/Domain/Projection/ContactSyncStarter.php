<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Projection;

use HiEvents\DomainObjects\CrmOrganizerStateDomainObject;
use HiEvents\DomainObjects\Generated\CrmOrganizerStateDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Jobs\SeedContactSyncJob;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmOrganizerStateRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmProjectionRepositoryInterface;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

class ContactSyncStarter
{
    public function __construct(
        private readonly CrmOrganizerStateRepositoryInterface $organizerStateRepository,
        private readonly CrmProjectionRepositoryInterface $projectionRepository,
        private readonly ContactSyncStatusService $syncStatusService,
    ) {}

    public function start(CrmOrganizerStateDomainObject $state): void
    {
        if (! $this->projectionRepository->hasSyncSources($state->getOrganizerId())) {
            $this->syncStatusService->markSynced($state->getId(), now(), $state->getSyncJobUuid());

            return;
        }

        $stateId = $state->getId();

        $batch = Bus::batch([new SeedContactSyncJob($state->getAccountId(), $state->getOrganizerId())])
            ->name('crm-contact-sync-'.$state->getOrganizerId())
            ->then(static function (Batch $batch) use ($stateId): void {
                app(ContactSyncStatusService::class)->markSynced($stateId, $batch->createdAt, $batch->id);
            })
            ->dispatch();

        $this->organizerStateRepository->updateWhere(
            attributes: [CrmOrganizerStateDomainObjectAbstract::SYNC_JOB_UUID => $batch->id],
            where: [CrmOrganizerStateDomainObjectAbstract::ID => $stateId],
        );
    }
}

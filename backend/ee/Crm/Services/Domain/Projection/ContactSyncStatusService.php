<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Projection;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\CrmOrganizerStateDomainObject;
use HiEvents\DomainObjects\Generated\CrmOrganizerStateDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmOrganizerStateRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Sync\DTO\ContactSyncStatusDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;
use HiEvents\Enterprise\Licensing\LicensedFeatureUsageService;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;

class ContactSyncStatusService
{
    private const COUNTS_CACHE_SECONDS = 5;

    public function __construct(
        private readonly CrmOrganizerStateRepositoryInterface $organizerStateRepository,
        private readonly CrmContactRepositoryInterface $contactRepository,
        private readonly CrmCacheVersion $cacheVersion,
        private readonly LicensedFeatureUsageService $featureUsage,
    ) {}

    public function getStatus(int $organizerId): ContactSyncStatusDTO
    {
        $state = $this->organizerStateRepository->findFirstWhere([
            CrmOrganizerStateDomainObjectAbstract::ORGANIZER_ID => $organizerId,
        ]);

        if ($state === null) {
            return $this->status(ContactSyncStatusDTO::NOT_STARTED, null, $organizerId, 0);
        }

        [$status, $syncedAt, $progress] = $this->describe($state);

        return $this->status($status, $syncedAt, $organizerId, $progress);
    }

    /**
     * @return array{0: string, 1: ?string, 2: int}
     */
    public function settle(CrmOrganizerStateDomainObject $state): array
    {
        $batch = $state->getSyncJobUuid() === null ? null : Bus::findBatch($state->getSyncJobUuid());

        if ($batch !== null && $this->hasSucceeded($batch)) {
            return [ContactSyncStatusDTO::READY, $this->markSynced($state->getId(), $batch->createdAt, $batch->id) ?? $state->getSyncedAt(), 100];
        }

        return $this->describe($state);
    }

    public function markSynced(int $stateId, CarbonInterface $startedAt, ?string $syncJobUuid): ?string
    {
        $syncedAt = now()->toDateTimeString();

        $updated = $this->organizerStateRepository->updateWhere(
            attributes: [
                CrmOrganizerStateDomainObjectAbstract::SYNCED_AT => $syncedAt,
                CrmOrganizerStateDomainObjectAbstract::SYNC_JOB_UUID => null,
            ],
            where: [
                CrmOrganizerStateDomainObjectAbstract::ID => $stateId,
                CrmOrganizerStateDomainObjectAbstract::SYNC_JOB_UUID => $syncJobUuid,
            ],
        );

        if ($updated === 0) {
            return null;
        }

        $this->organizerStateRepository->advanceReconciledUntil($stateId, $startedAt->copy()->subSeconds(ContactReconcileService::SETTLE_SECONDS));
        $this->featureUsage->forget();

        return $syncedAt;
    }

    /**
     * @return array{0: string, 1: ?string, 2: int}
     */
    private function describe(CrmOrganizerStateDomainObject $state): array
    {
        if ($state->getSyncJobUuid() === null) {
            return $state->getSyncedAt() === null
                ? [ContactSyncStatusDTO::NOT_STARTED, null, 0]
                : [ContactSyncStatusDTO::READY, $state->getSyncedAt(), 100];
        }

        $batch = Bus::findBatch($state->getSyncJobUuid());

        if ($batch === null || $batch->cancelled() || $batch->failedJobs > 0) {
            return [ContactSyncStatusDTO::FAILED, $state->getSyncedAt(), 0];
        }

        if ($batch->finished()) {
            return [ContactSyncStatusDTO::READY, $state->getSyncedAt() ?? $batch->finishedAt?->toDateTimeString(), 100];
        }

        return [ContactSyncStatusDTO::SYNCING, $state->getSyncedAt(), $batch->progress()];
    }

    private function hasSucceeded(Batch $batch): bool
    {
        return $batch->finished() && ! $batch->cancelled() && $batch->failedJobs === 0;
    }

    private function status(string $status, ?string $syncedAt, int $organizerId, int $progress): ContactSyncStatusDTO
    {
        $counts = $this->cacheVersion->remember(
            $organizerId,
            'sync-counts',
            self::COUNTS_CACHE_SECONDS,
            function () use ($organizerId): array {
                $counts = $this->contactRepository->countSegment($organizerId, null);

                return [(int) $counts->contacts_count, (int) $counts->mailable_count];
            },
        );

        return new ContactSyncStatusDTO(
            status: $status,
            synced_at: $syncedAt,
            contacts_count: $counts[0],
            mailable_count: $counts[1],
            progress_percent: $progress,
        );
    }
}

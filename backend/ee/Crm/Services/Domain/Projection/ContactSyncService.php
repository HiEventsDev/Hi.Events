<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Projection;

use HiEvents\DomainObjects\Enums\CrmProjectionOutcome;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Generated\CrmOrganizerStateDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmContactRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmOrganizerStateRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmProjectionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Domain\Cache\CrmCacheVersion;
use HiEvents\Enterprise\Crm\Services\Domain\Identity\DeletedContactHasher;
use HiEvents\Services\Domain\FeatureFlag\FeatureFlagService;

class ContactSyncService
{
    private const HISTORY_CHUNK_SIZE = 500;

    public function __construct(
        private readonly CrmProjectionRepositoryInterface $projectionRepository,
        private readonly CrmOrganizerStateRepositoryInterface $organizerStateRepository,
        private readonly DeletedContactHasher $deletedContactHasher,
        private readonly FeatureFlagService $featureFlagService,
        private readonly CrmCacheVersion $cacheVersion,
        private readonly CrmContactRepositoryInterface $contactRepository,
    ) {}

    public function syncOrder(int $orderId, bool $waitForLock = true): bool
    {
        $scope = $this->projectionRepository->findOrderScope($orderId);

        if ($scope === null) {
            return true;
        }

        return $this->projectOrders((int) $scope->account_id, (int) $scope->organizer_id, [$orderId], $waitForLock);
    }

    /**
     * @param  int[]  $orderIds
     */
    public function projectOrders(int $accountId, int $organizerId, array $orderIds, bool $waitForLock = true): bool
    {
        if (! $this->isOrganizerTracked($accountId, $organizerId)) {
            return true;
        }

        $outcome = $this->projectionRepository->projectOrders(
            accountId: $accountId,
            organizerId: $organizerId,
            orderIds: $orderIds,
            deletedContactSalt: $this->deletedContactHasher->salt($organizerId),
            now: now(),
            waitForLock: $waitForLock,
        );

        return $this->applyOutcome($organizerId, $outcome);
    }

    /**
     * @param  int[]  $entryIds
     */
    public function projectWaitlistEntries(int $accountId, int $organizerId, array $entryIds, bool $waitForLock = true): bool
    {
        if (! $this->isOrganizerTracked($accountId, $organizerId)) {
            return true;
        }

        $outcome = $this->projectionRepository->projectWaitlistEntries(
            accountId: $accountId,
            organizerId: $organizerId,
            entryIds: $entryIds,
            deletedContactSalt: $this->deletedContactHasher->salt($organizerId),
            now: now(),
            waitForLock: $waitForLock,
        );

        return $this->applyOutcome($organizerId, $outcome);
    }

    public function projectEmailHistory(int $accountId, int $organizerId, string $email): void
    {
        $this->contactRepository->relinkDeletedRecipients($organizerId, $email, $this->deletedContactHasher->hash($organizerId, $email));

        foreach (array_chunk($this->projectionRepository->findOrderIdsForEmail($organizerId, $email), self::HISTORY_CHUNK_SIZE) as $orderIds) {
            $this->projectOrders($accountId, $organizerId, $orderIds);
        }

        foreach (array_chunk($this->projectionRepository->findWaitlistEntryIdsForEmail($organizerId, $email), self::HISTORY_CHUNK_SIZE) as $entryIds) {
            $this->projectWaitlistEntries($accountId, $organizerId, $entryIds);
        }
    }

    private function applyOutcome(int $organizerId, CrmProjectionOutcome $outcome): bool
    {
        if ($outcome === CrmProjectionOutcome::CHANGED) {
            $this->cacheVersion->bump($organizerId);
        }

        return $outcome !== CrmProjectionOutcome::LOCK_BUSY;
    }

    private function isOrganizerTracked(int $accountId, int $organizerId): bool
    {
        if (! $this->featureFlagService->isEnabled(FeatureFlag::CRM, $accountId)) {
            return false;
        }

        return $this->organizerStateRepository->countWhere([
            CrmOrganizerStateDomainObjectAbstract::ORGANIZER_ID => $organizerId,
        ]) > 0;
    }
}

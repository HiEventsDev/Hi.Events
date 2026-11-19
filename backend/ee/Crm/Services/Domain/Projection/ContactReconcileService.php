<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Services\Domain\Projection;

use HiEvents\DomainObjects\CrmOrganizerStateDomainObject;
use HiEvents\DomainObjects\Enums\FeatureFlag;
use HiEvents\DomainObjects\Generated\CrmOrganizerStateDomainObjectAbstract;
use HiEvents\Enterprise\Crm\Jobs\ProjectOrdersJob;
use HiEvents\Enterprise\Crm\Jobs\ProjectWaitlistEntriesJob;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmOrganizerStateRepositoryInterface;
use HiEvents\Enterprise\Crm\Repository\Interfaces\CrmProjectionRepositoryInterface;
use HiEvents\Enterprise\Crm\Services\Application\Handlers\Sync\DTO\ContactSyncStatusDTO;
use HiEvents\Enterprise\Crm\Services\Domain\Projection\DTO\ReconcileWindowDTO;
use HiEvents\Services\Domain\FeatureFlag\FeatureFlagService;
use Illuminate\Support\Carbon;

class ContactReconcileService
{
    public const MAX_CATCH_UP_HOURS = 24;

    public const SETTLE_SECONDS = 30;

    private const PAGE_SIZE = 500;

    private const MAX_PAGES_PER_RUN = 20;

    public function __construct(
        private readonly CrmOrganizerStateRepositoryInterface $organizerStateRepository,
        private readonly CrmProjectionRepositoryInterface $projectionRepository,
        private readonly FeatureFlagService $featureFlagService,
        private readonly ContactSyncStatusService $syncStatusService,
        private readonly ContactSyncStarter $syncStarter,
    ) {}

    public function reconcile(int $organizerId, ?ReconcileWindowDTO $window): ?ReconcileWindowDTO
    {
        $state = $this->organizerStateRepository->findFirstWhere([
            CrmOrganizerStateDomainObjectAbstract::ORGANIZER_ID => $organizerId,
        ]);

        if ($state === null) {
            return null;
        }

        [$syncStatus, $syncedAt] = $this->syncStatusService->settle($state);

        if ($syncedAt === null || ! $this->featureFlagService->isEnabled(FeatureFlag::CRM, $state->getAccountId())) {
            return null;
        }

        $window ??= $this->openWindow($state, $syncStatus, $syncedAt);

        if ($window === null) {
            return null;
        }

        return $this->projectChanges($state, $window);
    }

    private function openWindow(CrmOrganizerStateDomainObject $state, string $syncStatus, string $syncedAt): ?ReconcileWindowDTO
    {
        $since = Carbon::parse($state->getReconciledUntil() ?? $syncedAt);
        $until = now()->subSeconds(self::SETTLE_SECONDS)->startOfSecond();

        if ($until->lessThanOrEqualTo($since)) {
            return null;
        }

        if ($since->lessThan($until->copy()->subHours(self::MAX_CATCH_UP_HOURS))) {
            if ($syncStatus !== ContactSyncStatusDTO::SYNCING) {
                $this->syncStarter->start($state);
            }

            return null;
        }

        return new ReconcileWindowDTO(since: $since->toDateTimeString(), until: $until->toDateTimeString());
    }

    private function projectChanges(CrmOrganizerStateDomainObject $state, ReconcileWindowDTO $window): ?ReconcileWindowDTO
    {
        $since = Carbon::parse($window->since);
        $until = Carbon::parse($window->until);
        $afterOrderId = $window->after_order_id;
        $afterEntryId = $window->after_entry_id;
        $ordersDone = $window->orders_done;

        for ($page = 0; $page < self::MAX_PAGES_PER_RUN; $page++) {
            if (! $ordersDone) {
                $orderIds = $this->projectionRepository->findOrderIdsChangedSince($state->getOrganizerId(), $since, $until, $afterOrderId, self::PAGE_SIZE);

                if ($orderIds !== []) {
                    ProjectOrdersJob::dispatch($state->getAccountId(), $state->getOrganizerId(), $orderIds);
                    $afterOrderId = end($orderIds);
                }

                $ordersDone = count($orderIds) < self::PAGE_SIZE;

                continue;
            }

            $entryIds = $this->projectionRepository->findWaitlistEntryIdsChangedSince($state->getOrganizerId(), $since, $until, $afterEntryId, self::PAGE_SIZE);

            if ($entryIds !== []) {
                ProjectWaitlistEntriesJob::dispatch($state->getAccountId(), $state->getOrganizerId(), $entryIds);
                $afterEntryId = end($entryIds);
            }

            if (count($entryIds) < self::PAGE_SIZE) {
                $this->organizerStateRepository->advanceReconciledUntil($state->getId(), $until);

                return null;
            }
        }

        return new ReconcileWindowDTO(
            since: $window->since,
            until: $window->until,
            after_order_id: $afterOrderId,
            after_entry_id: $afterEntryId,
            orders_done: $ordersDone,
        );
    }
}

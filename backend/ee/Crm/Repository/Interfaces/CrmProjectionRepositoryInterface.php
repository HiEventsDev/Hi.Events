<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Interfaces;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\CrmContactDomainObject;
use HiEvents\DomainObjects\Enums\CrmProjectionOutcome;
use HiEvents\Repository\Interfaces\RepositoryInterface;

/**
 * @extends RepositoryInterface<CrmContactDomainObject>
 */
interface CrmProjectionRepositoryInterface extends RepositoryInterface
{
    /**
     * @return object{organizer_id: int, account_id: int}|null
     */
    public function findOrderScope(int $orderId): ?object;

    public function hasSyncSources(int $organizerId): bool;

    /**
     * @return int[]
     */
    public function findEventIdsForOrganizer(int $organizerId, int $afterEventId, int $limit): array;

    /**
     * @return int[]
     */
    public function findOrderIdsForEvent(int $eventId, int $afterOrderId, int $limit): array;

    /**
     * @return int[]
     */
    public function findWaitlistEntryIdsForEvent(int $eventId, int $afterEntryId, int $limit): array;

    /**
     * @return int[]
     */
    public function findOrderIdsChangedSince(int $organizerId, CarbonInterface $since, CarbonInterface $until, int $afterOrderId, int $limit): array;

    /**
     * @return int[]
     */
    /**
     * @return int[]
     */
    public function findProjectedOrderIdsNoLongerLive(int $organizerId, int $afterOrderId, int $limit): array;

    /**
     * @return int[]
     */
    public function findProjectedWaitlistEntryIdsNoLongerLive(int $organizerId, int $afterEntryId, int $limit): array;

    public function findOrderIdsForEmail(int $organizerId, string $email): array;

    /**
     * @return int[]
     */
    public function findWaitlistEntryIdsForEmail(int $organizerId, string $email): array;

    /**
     * @return int[]
     */
    public function findWaitlistEntryIdsChangedSince(int $organizerId, CarbonInterface $since, CarbonInterface $until, int $afterEntryId, int $limit): array;

    /**
     * @param  int[]  $orderIds
     */
    public function projectOrders(int $accountId, int $organizerId, array $orderIds, string $deletedContactSalt, CarbonInterface $now, bool $waitForLock = true): CrmProjectionOutcome;

    /**
     * @param  int[]  $entryIds
     */
    public function projectWaitlistEntries(int $accountId, int $organizerId, array $entryIds, string $deletedContactSalt, CarbonInterface $now, bool $waitForLock = true): CrmProjectionOutcome;

    public function lockOrganizer(int $organizerId): void;

    /**
     * @param  int[]  $contactIds
     */
    public function refreshContacts(int $organizerId, array $contactIds): void;
}

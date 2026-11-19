<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Interfaces;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\CrmBulkOperationDomainObject;
use HiEvents\Repository\Interfaces\RepositoryInterface;

/**
 * @extends RepositoryInterface<CrmBulkOperationDomainObject>
 */
interface CrmBulkOperationRepositoryInterface extends RepositoryInterface
{
    public function failStalled(?int $organizerId = null): void;

    public function hasRunningDestructiveOperation(int $organizerId): bool;

    /**
     * @return int[] up to $limit unprocessed contact ids after $afterContactId, lowest first, now marked processed
     */
    public function claimNext(int $operationId, int $afterContactId, int $limit): array;

    /**
     * @param  int[]  $contactIds
     */
    public function recordProgress(int $operationId, int $processed, array $changedContactIds): void;

    public function completeIfFinished(int $operationId): bool;

    public function markFailed(int $operationId): void;

    /**
     * @return int[] up to $limit contact ids the operation changed, lowest first, no longer marked changed
     */
    public function claimNextChanged(int $operationId, int $limit): array;

    /**
     * @param  int[]  $contactIds
     * @param  int[]  $tagIds
     * @return int[] contacts that gained a tag
     */
    public function addTags(int $organizerId, array $contactIds, array $tagIds): array;

    /**
     * @param  int[]  $contactIds
     * @param  int[]  $tagIds
     * @return int[] contacts that lost a tag
     */
    public function removeTags(int $organizerId, array $contactIds, array $tagIds): array;

    public function lockOrganizer(int $organizerId): void;

    /**
     * @return int the snapshot rows deleted, at most $limit
     */
    public function pruneSnapshotsFinishedBefore(CarbonInterface $before, int $limit): int;
}

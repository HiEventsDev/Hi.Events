<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Interfaces;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\CrmOrganizerStateDomainObject;
use HiEvents\DomainObjects\Enums\CrmActivitySource;
use HiEvents\Repository\Interfaces\RepositoryInterface;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<CrmOrganizerStateDomainObject>
 */
interface CrmOrganizerStateRepositoryInterface extends RepositoryInterface
{
    public function createIfMissing(int $accountId, int $organizerId): CrmOrganizerStateDomainObject;

    /**
     * @return Collection<int, int> organizer ids keyed by state id
     */
    public function findOrganizerIdsAfter(int $afterStateId, int $limit): Collection;

    public function advanceReconciledUntil(int $stateId, CarbonInterface $until): void;

    public function isAudienceInUse(?int $accountId, CarbonInterface $now): bool;

    public function hasTrackedOrganizers(): bool;

    public function findTrackedOrderId(CrmActivitySource $source, int $sourceId): ?int;
}

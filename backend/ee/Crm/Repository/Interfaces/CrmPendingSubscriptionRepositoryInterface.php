<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Interfaces;

use Carbon\CarbonInterface;
use HiEvents\DomainObjects\CrmPendingSubscriptionDomainObject;
use HiEvents\Repository\Interfaces\RepositoryInterface;

/**
 * @extends RepositoryInterface<CrmPendingSubscriptionDomainObject>
 */
interface CrmPendingSubscriptionRepositoryInterface extends RepositoryInterface
{
    /**
     * @return int[] the forms whose pending signups were superseded
     */
    public function supersedePending(int $organizerId, string $email): array;

    /**
     * @return array<int, int> waiting signups keyed by form id
     */
    public function countWaitingByForm(int $organizerId, CarbonInterface $now): array;

    public function findPendingForUpdate(int $pendingId): ?CrmPendingSubscriptionDomainObject;

    public function pruneUnconfirmedExpiredBefore(CarbonInterface $before): int;

    public function hasConfirmationSince(int $organizerId, string $email, CarbonInterface $since): bool;
}

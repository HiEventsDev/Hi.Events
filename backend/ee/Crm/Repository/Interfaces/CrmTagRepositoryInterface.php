<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Crm\Repository\Interfaces;

use HiEvents\DomainObjects\CrmTagDomainObject;
use HiEvents\Repository\Interfaces\RepositoryInterface;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<CrmTagDomainObject>
 */
interface CrmTagRepositoryInterface extends RepositoryInterface
{
    /**
     * @return Collection<int, CrmTagDomainObject>
     */
    public function findByOrganizerIdWithCounts(int $organizerId): Collection;

    public function findIdByName(int $organizerId, string $name): ?int;

    public function nameExists(int $organizerId, string $name, ?int $exceptTagId = null): bool;

    /**
     * @param  int[]  $tagIds
     */
    public function countForOrganizer(int $organizerId, array $tagIds): int;

    /**
     * @return string[]
     */
    public function findRuleUsageNames(int $organizerId, int $tagId): array;

    public function removeFromSignupForms(int $organizerId, int $tagId): void;
}

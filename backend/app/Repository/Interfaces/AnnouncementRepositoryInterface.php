<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\AnnouncementDomainObject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<AnnouncementDomainObject>
 */
interface AnnouncementRepositoryInterface extends RepositoryInterface
{
    public function getAnnouncementsWithCounts(?string $search, int $perPage): LengthAwarePaginator;

    /**
     * @return Collection<int, AnnouncementDomainObject>
     */
    public function findActiveForUser(int $userId, int $accountId): Collection;
}

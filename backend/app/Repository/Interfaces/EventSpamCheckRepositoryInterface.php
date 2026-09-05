<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\EventSpamCheckDomainObject;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<EventSpamCheckDomainObject>
 */
interface EventSpamCheckRepositoryInterface extends RepositoryInterface
{
    public function getFlaggedEventsForAdmin(?string $search, int $perPage): LengthAwarePaginator;
}

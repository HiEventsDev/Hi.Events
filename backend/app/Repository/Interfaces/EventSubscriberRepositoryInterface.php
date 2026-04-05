<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\EventSubscriberDomainObject;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends RepositoryInterface<EventSubscriberDomainObject>
 */
interface EventSubscriberRepositoryInterface extends RepositoryInterface
{
    public function findByOrganizerId(int $organizerId, int $page = 1, int $perPage = 20): LengthAwarePaginator;

    public function findByToken(string $token): ?EventSubscriberDomainObject;

    public function subscriberExists(int $organizerId, string $email): bool;
}

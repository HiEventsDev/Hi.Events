<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\MembershipPlanDomainObject;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<MembershipPlanDomainObject>
 */
interface MembershipPlanRepositoryInterface extends RepositoryInterface
{
    public function findByOrganizerId(int $organizerId): Collection;
}

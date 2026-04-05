<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\MembershipDomainObject;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<MembershipDomainObject>
 */
interface MembershipRepositoryInterface extends RepositoryInterface
{
    public function findByEmail(string $email, int $accountId): Collection;

    public function findByPlanId(int $planId): Collection;
}

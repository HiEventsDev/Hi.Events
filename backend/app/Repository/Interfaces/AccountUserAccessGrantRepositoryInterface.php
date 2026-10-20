<?php

declare(strict_types=1);

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\AccountUserAccessGrantDomainObject;
use Illuminate\Support\Collection;

/**
 * @extends RepositoryInterface<AccountUserAccessGrantDomainObject>
 */
interface AccountUserAccessGrantRepositoryInterface extends RepositoryInterface
{
    /**
     * @param  int[]  $accountUserIds
     * @return Collection<int, AccountUserAccessGrantDomainObject>
     */
    public function findLiveGrantsForAccountUsers(array $accountUserIds): Collection;
}

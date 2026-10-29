<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccountUserPermissionDomainObject;
use HiEvents\Models\AccountUserPermission;
use HiEvents\Repository\Interfaces\AccountUserPermissionRepositoryInterface;

/**
 * @extends BaseRepository<AccountUserPermissionDomainObject>
 */
class AccountUserPermissionRepository extends BaseRepository implements AccountUserPermissionRepositoryInterface
{
    protected function getModel(): string
    {
        return AccountUserPermission::class;
    }

    public function getDomainObject(): string
    {
        return AccountUserPermissionDomainObject::class;
    }
}

<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\UserIdentityDomainObject;
use HiEvents\Models\UserIdentity;
use HiEvents\Repository\Interfaces\UserIdentityRepositoryInterface;

/**
 * @extends BaseRepository<UserIdentityDomainObject>
 */
class UserIdentityRepository extends BaseRepository implements UserIdentityRepositoryInterface
{
    protected function getModel(): string
    {
        return UserIdentity::class;
    }

    public function getDomainObject(): string
    {
        return UserIdentityDomainObject::class;
    }
}

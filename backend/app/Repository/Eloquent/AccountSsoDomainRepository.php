<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccountSsoDomainDomainObject;
use HiEvents\Models\AccountSsoDomain;
use HiEvents\Repository\Interfaces\AccountSsoDomainRepositoryInterface;

/**
 * @extends BaseRepository<AccountSsoDomainDomainObject>
 */
class AccountSsoDomainRepository extends BaseRepository implements AccountSsoDomainRepositoryInterface
{
    protected function getModel(): string
    {
        return AccountSsoDomain::class;
    }

    public function getDomainObject(): string
    {
        return AccountSsoDomainDomainObject::class;
    }
}

<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Sso\Repository\Eloquent;

use HiEvents\DomainObjects\AccountSsoDomainDomainObject;
use HiEvents\Enterprise\Sso\Repository\Interfaces\AccountSsoDomainRepositoryInterface;
use HiEvents\Models\AccountSsoDomain;
use HiEvents\Repository\Eloquent\BaseRepository;

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

<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccountSsoConnectionDomainObject;
use HiEvents\Models\AccountSsoConnection;
use HiEvents\Repository\Interfaces\AccountSsoConnectionRepositoryInterface;

/**
 * @extends BaseRepository<AccountSsoConnectionDomainObject>
 */
class AccountSsoConnectionRepository extends BaseRepository implements AccountSsoConnectionRepositoryInterface
{
    protected function getModel(): string
    {
        return AccountSsoConnection::class;
    }

    public function getDomainObject(): string
    {
        return AccountSsoConnectionDomainObject::class;
    }
}

<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Sso\Repository\Eloquent;

use HiEvents\DomainObjects\AccountSsoConnectionDomainObject;
use HiEvents\Enterprise\Sso\Repository\Interfaces\AccountSsoConnectionRepositoryInterface;
use HiEvents\Models\AccountSsoConnection;
use HiEvents\Repository\Eloquent\BaseRepository;

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

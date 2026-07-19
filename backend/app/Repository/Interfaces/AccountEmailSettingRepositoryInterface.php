<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\AccountEmailSettingDomainObject;

/**
 * @extends RepositoryInterface<AccountEmailSettingDomainObject>
 */
interface AccountEmailSettingRepositoryInterface extends RepositoryInterface
{
    public function findByAccountId(int $accountId): ?AccountEmailSettingDomainObject;
}

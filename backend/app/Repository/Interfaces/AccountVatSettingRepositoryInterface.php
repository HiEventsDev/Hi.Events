<?php

namespace HiEvents\Repository\Interfaces;

use HiEvents\DomainObjects\AccountVatSettingDomainObject;

interface AccountVatSettingRepositoryInterface extends RepositoryInterface
{
    public function findByAccountId(int $accountId): ?AccountVatSettingDomainObject;
}

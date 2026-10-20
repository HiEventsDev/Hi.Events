<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccountEmailSettingDomainObject;
use HiEvents\Models\AccountEmailSetting;
use HiEvents\Repository\Interfaces\AccountEmailSettingRepositoryInterface;

/**
 * @extends BaseRepository<AccountEmailSettingDomainObject>
 */
class AccountEmailSettingRepository extends BaseRepository implements AccountEmailSettingRepositoryInterface
{
    protected function getModel(): string
    {
        return AccountEmailSetting::class;
    }

    public function getDomainObject(): string
    {
        return AccountEmailSettingDomainObject::class;
    }

    public function findByAccountId(int $accountId): ?AccountEmailSettingDomainObject
    {
        return $this->findFirstWhere(['account_id' => $accountId]);
    }
}

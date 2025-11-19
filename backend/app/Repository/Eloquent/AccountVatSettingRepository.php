<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccountVatSettingDomainObject;
use HiEvents\Models\AccountVatSetting;
use HiEvents\Repository\Interfaces\AccountVatSettingRepositoryInterface;

class AccountVatSettingRepository extends BaseRepository implements AccountVatSettingRepositoryInterface
{
    protected function getModel(): string
    {
        return AccountVatSetting::class;
    }

    public function getDomainObject(): string
    {
        return AccountVatSettingDomainObject::class;
    }

    public function findByAccountId(int $accountId): ?AccountVatSettingDomainObject
    {
        return $this->findFirstWhere(['account_id' => $accountId]);
    }
}

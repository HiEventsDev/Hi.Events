<?php

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccountConfigurationDomainObject;
use HiEvents\Models\AccountConfiguration;
use HiEvents\Repository\Interfaces\AccountConfigurationRepositoryInterface;

class AccountConfigurationRepository extends BaseRepository implements AccountConfigurationRepositoryInterface
{
    protected function getModel(): string
    {
        return AccountConfiguration::class;
    }

    public function getDomainObject(): string
    {
        return AccountConfigurationDomainObject::class;
    }
}

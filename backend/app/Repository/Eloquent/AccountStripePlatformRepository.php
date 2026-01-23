<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccountStripePlatformDomainObject;
use HiEvents\Models\AccountStripePlatform;
use HiEvents\Repository\Interfaces\AccountStripePlatformRepositoryInterface;

class AccountStripePlatformRepository extends BaseRepository implements AccountStripePlatformRepositoryInterface
{
    protected function getModel(): string
    {
        return AccountStripePlatform::class;
    }

    public function getDomainObject(): string
    {
        return AccountStripePlatformDomainObject::class;
    }
}

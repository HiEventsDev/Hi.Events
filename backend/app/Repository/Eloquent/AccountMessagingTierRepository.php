<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccountMessagingTierDomainObject;
use HiEvents\Models\AccountMessagingTier;
use HiEvents\Repository\Interfaces\AccountMessagingTierRepositoryInterface;

class AccountMessagingTierRepository extends BaseRepository implements AccountMessagingTierRepositoryInterface
{
    protected function getModel(): string
    {
        return AccountMessagingTier::class;
    }

    public function getDomainObject(): string
    {
        return AccountMessagingTierDomainObject::class;
    }
}

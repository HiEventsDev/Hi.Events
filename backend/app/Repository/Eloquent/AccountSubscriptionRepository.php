<?php

declare(strict_types=1);

namespace HiEvents\Repository\Eloquent;

use HiEvents\DomainObjects\AccountSubscriptionDomainObject;
use HiEvents\Models\AccountSubscription;
use HiEvents\Repository\Interfaces\AccountSubscriptionRepositoryInterface;

/**
 * @extends BaseRepository<AccountSubscriptionDomainObject>
 */
class AccountSubscriptionRepository extends BaseRepository implements AccountSubscriptionRepositoryInterface
{
    protected function getModel(): string
    {
        return AccountSubscription::class;
    }

    public function getDomainObject(): string
    {
        return AccountSubscriptionDomainObject::class;
    }
}

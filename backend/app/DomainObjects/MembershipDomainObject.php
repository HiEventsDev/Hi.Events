<?php

declare(strict_types=1);

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Generated\MembershipDomainObjectAbstract;

class MembershipDomainObject extends MembershipDomainObjectAbstract
{
    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->expires_at !== null && strtotime($this->expires_at) < time()) {
            return false;
        }

        return true;
    }

    public function hasEventCapacity(): bool
    {
        // If max_events is not set on plan, unlimited access
        return true;
    }
}

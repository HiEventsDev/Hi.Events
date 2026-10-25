<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Status\AccountSubscriptionStatus;

class AccountSubscriptionDomainObject extends Generated\AccountSubscriptionDomainObjectAbstract
{
    public function grantsAccess(): bool
    {
        return $this->status()?->grantsAccess() ?? false;
    }

    public function hasOpenSubscription(): bool
    {
        return $this->status()?->isOpen() ?? false;
    }

    private function status(): ?AccountSubscriptionStatus
    {
        return AccountSubscriptionStatus::tryFrom((string) $this->getStatus());
    }
}

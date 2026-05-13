<?php

namespace HiEvents\DomainObjects;

class AccountDomainObject extends Generated\AccountDomainObjectAbstract
{
    private ?AccountMessagingTierDomainObject $messagingTier = null;

    public function getMessagingTier(): ?AccountMessagingTierDomainObject
    {
        return $this->messagingTier;
    }

    public function setMessagingTier(AccountMessagingTierDomainObject $messagingTier): void
    {
        $this->messagingTier = $messagingTier;
    }
}

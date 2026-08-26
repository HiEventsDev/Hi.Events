<?php

namespace HiEvents\DomainObjects;

class AccountDomainObject extends Generated\AccountDomainObjectAbstract
{
    private ?AccountMessagingTierDomainObject $messagingTier = null;

    private ?AccountDeletionRequestDomainObject $activeDeletionRequest = null;

    public function getMessagingTier(): ?AccountMessagingTierDomainObject
    {
        return $this->messagingTier;
    }

    public function setMessagingTier(AccountMessagingTierDomainObject $messagingTier): void
    {
        $this->messagingTier = $messagingTier;
    }

    public function getActiveDeletionRequest(): ?AccountDeletionRequestDomainObject
    {
        return $this->activeDeletionRequest;
    }

    public function setActiveDeletionRequest(?AccountDeletionRequestDomainObject $activeDeletionRequest): void
    {
        $this->activeDeletionRequest = $activeDeletionRequest;
    }
}

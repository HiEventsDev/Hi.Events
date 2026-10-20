<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Enums\SsoSignInRequirement;

class AccountUserDomainObject extends Generated\AccountUserDomainObjectAbstract
{
    public ?AccountDomainObject $account = null;

    public ?UserDomainObject $user = null;

    private ?SsoSignInRequirement $ssoSignInRequirement = null;

    public function getAccount(): ?AccountDomainObject
    {
        return $this->account;
    }

    public function setAccount(?AccountDomainObject $account): static
    {
        $this->account = $account;

        return $this;
    }

    public function getUser(): ?UserDomainObject
    {
        return $this->user;
    }

    public function setUser(?UserDomainObject $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getSsoSignInRequirement(): ?SsoSignInRequirement
    {
        return $this->ssoSignInRequirement;
    }

    public function setSsoSignInRequirement(?SsoSignInRequirement $ssoSignInRequirement): static
    {
        $this->ssoSignInRequirement = $ssoSignInRequirement;

        return $this;
    }
}

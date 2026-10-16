<?php

namespace HiEvents\DomainObjects;

class AccountUserDomainObject extends Generated\AccountUserDomainObjectAbstract
{
    public ?AccountDomainObject $account = null;

    public ?UserDomainObject $user = null;

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
}

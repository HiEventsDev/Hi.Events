<?php

namespace HiEvents\DomainObjects;

class AccountUserDomainObject extends Generated\AccountUserDomainObjectAbstract
{
    public ?AccountDomainObject $account = null;

    public function getAccount(): ?AccountDomainObject
    {
        return $this->account;
    }

    public function setAccount(?AccountDomainObject $account): static
    {
        $this->account = $account;

        return $this;
    }
}

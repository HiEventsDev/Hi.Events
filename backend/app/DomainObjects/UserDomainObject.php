<?php

namespace HiEvents\DomainObjects;

use Illuminate\Support\Collection;

class UserDomainObject extends Generated\UserDomainObjectAbstract
{
    public ?Collection $accounts = null;

    public ?AccountUserDomainObject $currentAccountUser = null;

    public function getFullName(): string
    {
        return $this->getFirstName() . ' ' . $this->getLastName();
    }

    public function setPendingEmail(?string $pending_email): Generated\UserDomainObjectAbstract
    {
        return parent::setPendingEmail($pending_email === null ? null : strtolower($pending_email));
    }

    public function setEmail(string $email): Generated\UserDomainObjectAbstract
    {
        return parent::setEmail(strtolower($email));
    }

    /**
     * @return Collection<AccountDomainObject>|null
     */
    public function getAccounts(): ?Collection
    {
        return $this->accounts;
    }

    public function setAccounts(?Collection $accounts): static
    {
        $this->accounts = $accounts;

        return $this;
    }

    public function getCurrentAccountUser(): ?AccountUserDomainObject
    {
        return $this->currentAccountUser;
    }

    public function setCurrentAccountUser(?AccountUserDomainObject $currentAccountUser): static
    {
        $this->currentAccountUser = $currentAccountUser;

        return $this;
    }
}

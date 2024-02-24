<?php

namespace HiEvents\DomainObjects;

class UserDomainObject extends Generated\UserDomainObjectAbstract
{
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
}

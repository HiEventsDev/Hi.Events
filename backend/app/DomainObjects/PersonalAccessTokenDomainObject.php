<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Enums\ApiKeyAccess;

class PersonalAccessTokenDomainObject extends Generated\PersonalAccessTokenDomainObjectAbstract
{
    private ?UserDomainObject $user = null;

    public function getAccess(): ApiKeyAccess
    {
        return ApiKeyAccess::fromAbilities(json_decode($this->getAbilities() ?? '[]', true) ?: []);
    }

    public function setUser(?UserDomainObject $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getUser(): ?UserDomainObject
    {
        return $this->user;
    }
}

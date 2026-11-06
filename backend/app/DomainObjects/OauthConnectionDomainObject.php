<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Enums\ApiKeyAccess;

class OauthConnectionDomainObject extends Generated\OauthConnectionDomainObjectAbstract
{
    private ?OauthClientDomainObject $oauthClient = null;

    private ?UserDomainObject $user = null;

    public function getAccessLevel(): ApiKeyAccess
    {
        return ApiKeyAccess::from($this->getAccess());
    }

    public function setOauthClient(?OauthClientDomainObject $oauthClient): self
    {
        $this->oauthClient = $oauthClient;

        return $this;
    }

    public function getOauthClient(): ?OauthClientDomainObject
    {
        return $this->oauthClient;
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

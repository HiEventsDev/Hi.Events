<?php

namespace HiEvents\DomainObjects;

class CrmImportDomainObject extends Generated\CrmImportDomainObjectAbstract
{
    private ?string $userName = null;

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function setUserName(?string $userName): static
    {
        $this->userName = $userName;

        return $this;
    }
}

<?php

namespace HiEvents\DomainObjects;

class CrmTagDomainObject extends Generated\CrmTagDomainObjectAbstract
{
    private int $contactsCount = 0;

    public function getContactsCount(): int
    {
        return $this->contactsCount;
    }

    public function setContactsCount(int $contactsCount): static
    {
        $this->contactsCount = $contactsCount;

        return $this;
    }
}

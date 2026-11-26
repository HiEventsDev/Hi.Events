<?php

namespace HiEvents\DomainObjects;

class CrmSignupFormDomainObject extends Generated\CrmSignupFormDomainObjectAbstract
{
    private ?OrganizerDomainObject $organizer = null;

    private ?int $waitingCount = null;

    public function getWaitingCount(): ?int
    {
        return $this->waitingCount;
    }

    public function setWaitingCount(?int $waitingCount): self
    {
        $this->waitingCount = $waitingCount;

        return $this;
    }

    public function getOrganizer(): ?OrganizerDomainObject
    {
        return $this->organizer;
    }

    public function setOrganizer(?OrganizerDomainObject $organizer): self
    {
        $this->organizer = $organizer;

        return $this;
    }
}

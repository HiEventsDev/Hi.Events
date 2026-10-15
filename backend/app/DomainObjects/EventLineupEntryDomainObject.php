<?php

namespace HiEvents\DomainObjects;

class EventLineupEntryDomainObject extends Generated\EventLineupEntryDomainObjectAbstract
{
    private ?ProfileDomainObject $profile = null;

    private ?EventDomainObject $event = null;

    public function getEvent(): ?EventDomainObject
    {
        return $this->event;
    }

    public function setEvent(?EventDomainObject $event): self
    {
        $this->event = $event;

        return $this;
    }

    public function getProfile(): ?ProfileDomainObject
    {
        return $this->profile;
    }

    public function setProfile(?ProfileDomainObject $profile): self
    {
        $this->profile = $profile;

        return $this;
    }
}

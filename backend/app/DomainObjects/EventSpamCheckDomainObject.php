<?php

namespace HiEvents\DomainObjects;

class EventSpamCheckDomainObject extends Generated\EventSpamCheckDomainObjectAbstract
{
    private ?EventDomainObject $event = null;

    public function setEvent(?EventDomainObject $event): self
    {
        $this->event = $event;

        return $this;
    }

    public function getEvent(): ?EventDomainObject
    {
        return $this->event;
    }
}

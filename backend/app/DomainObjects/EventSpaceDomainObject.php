<?php

namespace HiEvents\DomainObjects;

class EventSpaceDomainObject extends Generated\EventSpaceDomainObjectAbstract
{
    private ?EventLocationDomainObject $eventLocation = null;

    public function getEventLocation(): ?EventLocationDomainObject
    {
        return $this->eventLocation;
    }

    public function setEventLocation(?EventLocationDomainObject $eventLocation): self
    {
        $this->eventLocation = $eventLocation;

        return $this;
    }
}

<?php

namespace HiEvents\DomainObjects;

use Illuminate\Support\Collection;

class OrganizerDomainObject extends Generated\OrganizerDomainObjectAbstract
{
    private ?Collection $images = null;

    /**
     * @return Collection<EventDomainObject>|null
     */
    private ?Collection $events = null;

    public function getImages(): ?Collection
    {
        return $this->images;
    }

    public function setImages(?Collection $images): self
    {
        $this->images = $images;

        return $this;
    }

    public function getEvents(): ?Collection
    {
        return $this->events;
    }

    public function setEvents(?Collection $events): self
    {
        $this->events = $events;

        return $this;
    }
}

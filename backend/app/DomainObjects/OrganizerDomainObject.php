<?php

namespace TicketKitten\DomainObjects;

use Illuminate\Support\Collection;

class OrganizerDomainObject extends Generated\OrganizerDomainObjectAbstract
{
    private ?Collection $images = null;

    public function getImages(): ?Collection
    {
        return $this->images;
    }

    public function setImages(?Collection $images): self
    {
        $this->images = $images;

        return $this;
    }
}

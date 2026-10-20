<?php

namespace HiEvents\DomainObjects;

use Illuminate\Support\Collection;

class EventScheduleItemDomainObject extends Generated\EventScheduleItemDomainObjectAbstract
{
    private ?Collection $performers = null;

    public function getPerformers(): ?Collection
    {
        return $this->performers;
    }

    public function setPerformers(?Collection $performers): self
    {
        $this->performers = $performers;

        return $this;
    }
}

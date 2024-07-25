<?php

namespace HiEvents\DomainObjects;

use Illuminate\Support\Collection;

class CapacityAssignmentDomainObject extends Generated\CapacityAssignmentDomainObjectAbstract
{
    public ?Collection $tickets = null;

    public function getPercentageUsed(): float
    {
        if (!$this->getCapacity()) {
            return 0;
        }

        return round(($this->getUsedCapacity() / $this->getCapacity()) * 100, 2);
    }

    public function getTickets(): ?Collection
    {
        return $this->tickets;
    }

    public function setTickets(?Collection $tickets): static
    {
        $this->tickets = $tickets;

        return $this;
    }
}

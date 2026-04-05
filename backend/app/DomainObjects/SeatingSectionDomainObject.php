<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Generated\SeatingSectionDomainObjectAbstract;
use Illuminate\Support\Collection;

class SeatingSectionDomainObject extends SeatingSectionDomainObjectAbstract
{
    private ?Collection $seats = null;

    public function getSeats(): ?Collection
    {
        return $this->seats;
    }

    public function setSeats(?Collection $seats): self
    {
        $this->seats = $seats;
        return $this;
    }
}

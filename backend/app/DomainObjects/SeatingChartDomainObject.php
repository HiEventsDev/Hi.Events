<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Generated\SeatingChartDomainObjectAbstract;
use Illuminate\Support\Collection;

class SeatingChartDomainObject extends SeatingChartDomainObjectAbstract
{
    private ?Collection $sections = null;

    public function getSections(): ?Collection
    {
        return $this->sections;
    }

    public function setSections(?Collection $sections): self
    {
        $this->sections = $sections;
        return $this;
    }
}

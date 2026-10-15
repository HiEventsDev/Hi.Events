<?php

namespace HiEvents\DomainObjects;

use Illuminate\Support\Collection;

class ProfileDomainObject extends Generated\ProfileDomainObjectAbstract
{
    private ?Collection $images = null;

    private ?Collection $lineupEntries = null;

    public function getLineupEntries(): ?Collection
    {
        return $this->lineupEntries;
    }

    public function setLineupEntries(?Collection $lineupEntries): self
    {
        $this->lineupEntries = $lineupEntries;

        return $this;
    }

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

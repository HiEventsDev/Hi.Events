<?php

declare(strict_types=1);

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Generated\EventLocationDomainObjectAbstract;

class EventLocationDomainObject extends EventLocationDomainObjectAbstract
{
    private ?LocationDomainObject $location = null;

    public function getLocation(): ?LocationDomainObject
    {
        return $this->location;
    }

    public function setLocation(?LocationDomainObject $location): self
    {
        $this->location = $location;

        return $this;
    }
}

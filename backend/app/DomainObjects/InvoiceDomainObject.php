<?php

namespace HiEvents\DomainObjects;

class InvoiceDomainObject extends Generated\InvoiceDomainObjectAbstract
{
    public ?OrderDomainObject $order = null;

    public ?EventDomainObject $event = null;

    public function getOrder(): ?OrderDomainObject
    {
        return $this->order;
    }

    public function setOrder(?OrderDomainObject $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function getEvent(): ?EventDomainObject
    {
        return $this->event;
    }

    public function setEvent(?EventDomainObject $event): self
    {
        $this->event = $event;

        return $this;
    }
}

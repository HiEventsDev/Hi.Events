<?php

namespace HiEvents\DomainObjects;

class OutgoingMessageDomainObject extends Generated\OutgoingMessageDomainObjectAbstract
{
    private ?EmailDeliveryDomainObject $emailDelivery = null;

    public function getEmailDelivery(): ?EmailDeliveryDomainObject
    {
        return $this->emailDelivery;
    }

    public function setEmailDelivery(?EmailDeliveryDomainObject $emailDelivery): self
    {
        $this->emailDelivery = $emailDelivery;

        return $this;
    }
}

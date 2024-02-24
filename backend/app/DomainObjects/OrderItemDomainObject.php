<?php

namespace HiEvents\DomainObjects;

use HiEvents\Helper\Currency;

class OrderItemDomainObject extends Generated\OrderItemDomainObjectAbstract
{
    private ?TicketPriceDomainObject $ticketPrice = null;

    public ?TicketDomainObject $ticket = null;

    public function getTotalBeforeDiscount(): float
    {
        return Currency::round($this->getPriceBeforeDiscount() * $this->getQuantity());
    }

    public function getTicketPrice(): ?TicketPriceDomainObject
    {
        return $this->ticketPrice;
    }

    public function setTicketPrice(?TicketPriceDomainObject $tier): self
    {
        $this->ticketPrice = $tier;

        return $this;
    }

    public function getTicket(): ?TicketDomainObject
    {
        return $this->ticket;
    }

    public function setTicket(?TicketDomainObject $ticket): self
    {
        $this->ticket = $ticket;

        return $this;
    }
}

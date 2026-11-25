<?php

namespace HiEvents\DomainObjects;

use HiEvents\DomainObjects\Enums\TicketResaleListingState;
use HiEvents\DomainObjects\Interfaces\IsFilterable;
use HiEvents\DomainObjects\Status\TicketResaleListingStatus;
use HiEvents\DomainObjects\Status\TicketResaleRefundStatus;

class TicketResaleListingDomainObject extends Generated\TicketResaleListingDomainObjectAbstract implements IsFilterable
{
    private ?AttendeeDomainObject $attendee = null;

    private ?OrderDomainObject $order = null;

    private ?OrderDomainObject $soldOrder = null;

    private ?TicketResaleListingState $state = null;

    private ?string $heldUntil = null;

    public static function getAllowedFilterFields(): array
    {
        return [
            self::STATUS,
        ];
    }

    public function hasFailedRefund(): bool
    {
        return $this->getStatus() === TicketResaleListingStatus::SOLD->name
            && $this->getRefundStatus() === TicketResaleRefundStatus::FAILED->name;
    }

    public function getAttendee(): ?AttendeeDomainObject
    {
        return $this->attendee;
    }

    public function setAttendee(?AttendeeDomainObject $attendee): self
    {
        $this->attendee = $attendee;

        return $this;
    }

    public function getOrder(): ?OrderDomainObject
    {
        return $this->order;
    }

    public function setOrder(?OrderDomainObject $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function getSoldOrder(): ?OrderDomainObject
    {
        return $this->soldOrder;
    }

    public function setSoldOrder(?OrderDomainObject $soldOrder): self
    {
        $this->soldOrder = $soldOrder;

        return $this;
    }

    public function getState(): ?TicketResaleListingState
    {
        return $this->state;
    }

    public function setState(TicketResaleListingState $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function getHeldUntil(): ?string
    {
        return $this->heldUntil;
    }

    public function setHeldUntil(?string $heldUntil): self
    {
        $this->heldUntil = $heldUntil;

        return $this;
    }
}

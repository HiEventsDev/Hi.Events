<?php

namespace HiEvents\DomainObjects;

use Carbon\Carbon;
use HiEvents\DomainObjects\Status\TicketTransferStatus;

class TicketTransferDomainObject extends Generated\TicketTransferDomainObjectAbstract
{
    public function isAwaitingRecipient(): bool
    {
        return $this->getStatus() === TicketTransferStatus::PENDING->name && ! $this->hasExpired();
    }

    public function hasExpired(): bool
    {
        return $this->getStatus() === TicketTransferStatus::EXPIRED->name
            || ($this->getStatus() === TicketTransferStatus::PENDING->name && Carbon::parse($this->getExpiresAt())->isPast());
    }
}

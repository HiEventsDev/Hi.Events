<?php

namespace HiEvents\Services\Domain\SelfService;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;

class TicketControlService
{
    public function controlsTicket(?string $verifiedEmail, AttendeeDomainObject $attendee, OrderDomainObject $order): bool
    {
        if ($this->isSameAddress($verifiedEmail, $attendee->getEmail())) {
            return true;
        }

        return $attendee->getTransferredAt() === null && $this->controlsOrder($verifiedEmail, $order);
    }

    public function controlsOrder(?string $verifiedEmail, OrderDomainObject $order): bool
    {
        return $this->isSameAddress($verifiedEmail, $order->getEmail());
    }

    private function isSameAddress(?string $verifiedEmail, ?string $emailOnFile): bool
    {
        return $verifiedEmail !== null
            && $emailOnFile !== null
            && mb_strtolower(trim($verifiedEmail)) === mb_strtolower(trim($emailOnFile));
    }
}

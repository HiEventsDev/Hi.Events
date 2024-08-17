<?php

namespace HiEvents\Resources\Attendee;

use HiEvents\DomainObjects\AttendeeDomainObject;
use HiEvents\Resources\Ticket\TicketMinimalResourcePublic;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttendeeDomainObject
 */
class AttendeeResourcePublic extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->getId(),
            'email' => $this->getEmail(),
            'status' => $this->getStatus(),
            'first_name' => $this->getFirstName(),
            'last_name' => $this->getLastName(),
            'public_id' => $this->getPublicId(),
            'short_id' => $this->getShortId(),
            'ticket_id' => $this->getTicketId(),
            'ticket_price_id' => $this->getTicketPriceId(),
            'ticket' => $this->when((bool)$this->getTicket(), fn() => new TicketMinimalResourcePublic($this->getTicket())),
            'locale' => $this->getLocale(),
        ];
    }
}

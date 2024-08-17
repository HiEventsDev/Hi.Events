<?php

namespace HiEvents\Resources\CheckInList;

use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\Resources\Event\EventResourcePublic;
use HiEvents\Resources\Ticket\TicketMinimalResourcePublic;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CheckInListDomainObject
 */
class CheckInListResourcePublic extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'short_id' => $this->getShortId(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'expires_at' => $this->getExpiresAt(),
            'activates_at' => $this->getActivatesAt(),
            'total_attendees' => $this->getTotalAttendeesCount(),
            'checked_in_attendees' => $this->getCheckedInCount(),
            $this->mergeWhen($this->getEvent() !== null, fn() => [
                'is_expired' => $this->isExpired($this->getEvent()->getTimezone()),
                'is_active' => $this->isActivated($this->getEvent()->getTimezone()),
                'event' => EventResourcePublic::make($this->getEvent()),
            ]),
            $this->mergeWhen($this->getTickets() !== null, fn() => [
                'tickets' => TicketMinimalResourcePublic::collection($this->getTickets()),
            ]),
        ];
    }
}

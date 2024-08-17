<?php

namespace HiEvents\Resources\CheckInList;

use HiEvents\DomainObjects\CheckInListDomainObject;
use HiEvents\Resources\Ticket\TicketResource;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CheckInListDomainObject
 */
class CheckInListResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'expires_at' => $this->getExpiresAt(),
            'activates_at' => $this->getActivatesAt(),
            'short_id' => $this->getShortId(),
            'total_attendees' => $this->getTotalAttendeesCount(),
            'checked_in_attendees' => $this->getCheckedInCount(),
            $this->mergeWhen($this->getEvent() !== null, fn() => [
                'is_expired' => $this->isExpired($this->getEvent()->getTimezone()),
                'is_active' => $this->isActivated($this->getEvent()->getTimezone()),
            ]),
            $this->mergeWhen($this->getTickets() !== null, fn() => [
                'tickets' => TicketResource::collection($this->getTickets()),
            ]),
        ];
    }
}

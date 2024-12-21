<?php

namespace HiEvents\Resources\CheckInList;

use HiEvents\DomainObjects\AttendeeCheckInDomainObject;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttendeeCheckInDomainObject
 */
class AttendeeCheckInResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->getId(),
            'attendee_id' => $this->getAttendeeId(),
            'check_in_list_id' => $this->getCheckInListId(),
            'ticket_id' => $this->getTicketId(),
            'event_id' => $this->getEventId(),
            'short_id' => $this->getShortId(),
            'created_at' => $this->getCreatedAt(),
        ];
    }
}
